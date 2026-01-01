<?php
// htdocs/api/users/verify_code.php
// Verify a numeric code (or token) from verification_tokens.
// Accepts JSON POST or form POST with fields:
//  - user_id (optional if jti provided)
//  - jti (optional if user_id provided)
//  - code (required)   // numeric code sent to user
//
// Returns JSON:
//  - on success: { success:true, message:'verified', user_id: N }
//  - on failure: { success:false, message:'reason', ... }
//
// Logging: writes diagnostics to htdocs/api/logs/verify_code.log
// Requires config/db.php for connectDB().

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
function vlog($msg) { 
    global $logDir; 
    @file_put_contents($logDir . '/verify_code.log', date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX); 
}

function reply($payload, $http_status = 200) {
    http_response_code($http_status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

// Read input (JSON preferred)
$raw = @file_get_contents('php://input');
$input = $_POST;
if ($raw && empty($input)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $j = @json_decode($raw, true);
        if (is_array($j)) $input = $j;
    } else {
        parse_str($raw, $parsed);
        if (is_array($parsed)) $input = array_merge($input, $parsed);
    }
}
vlog('request_input: ' . json_encode(['input'=>$input, 'CT'=>($_SERVER['CONTENT_TYPE'] ?? '')], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

// normalize params
$user_id = isset($input['user_id']) && $input['user_id'] !== '' ? (int)$input['user_id'] : null;
$jti = isset($input['jti']) && $input['jti'] !== '' ? trim((string)$input['jti']) : null;
$code = isset($input['code']) ? trim((string)$input['code']) : '';
$debug = !empty($input['debug']) || !empty($input['debug_verification']);

if ($code === '') {
    vlog('missing_code');
    reply(['success'=>false,'message'=>'code_required'], 400);
}
if (!$user_id && !$jti) {
    vlog('missing_userid_or_jti');
    reply(['success'=>false,'message'=>'user_id_or_jti_required'], 400);
}

require_once __DIR__ . '/../../api/config/db.php';
$mysqli = @connectDB();
if (!$mysqli) {
    vlog('db_connect_failed');
    reply(['success'=>false,'message'=>'db_connect_failed'], 500);
}

try {
    // If jti provided -> lookup specific token
    if ($jti) {
        $sql = "SELECT id, user_id, token_hash, used, attempts, expires_at, expires_at_local, user_tz FROM verification_tokens WHERE jti = ? LIMIT 1";
        $st = $mysqli->prepare($sql);
        if (!$st) { 
            vlog("prepare_failed jti: " . $mysqli->error . " | sql: $sql"); 
            reply(['success'=>false,'message'=>'db_prepare_failed'],500); 
        }
        $st->bind_param('s', $jti);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();

        if (!$row) {
            vlog("token_not_found jti={$jti}");
            reply(['success'=>false,'message'=>'token_not_found'],404);
        }
        
        vlog("Found token for jti={$jti}, user_id={$row['user_id']}, attempts={$row['attempts']}, used={$row['used']}");
        
        if (!empty($row['used'])) {
            vlog("token_already_used id={$row['id']}");
            reply(['success'=>false,'message'=>'token_already_used'],409);
        }
        
        // check expiry (stored as UTC datetime string)
        $expires_at = $row['expires_at'];
        $expires_ts = strtotime($expires_at);
        $now_ts = time();
        
        if (!empty($expires_at) && $expires_ts < $now_ts) {
            vlog("token_expired id={$row['id']} expires_at={$expires_at} (ts=$expires_ts) now_ts=$now_ts");
            reply([
                'success'=>false,
                'message'=>'token_expired',
                'expires_at'=>$expires_at,
                'expires_at_local'=>$row['expires_at_local'],
                'user_tz'=>$row['user_tz'],
                'server_time'=>date('Y-m-d H:i:s')
            ],410);
        }
        
        // Debug: log the hash and code attempt
        vlog("Verifying code: code='{$code}', token_hash=" . substr($row['token_hash'], 0, 20) . "...");
        
        $matches = false;
        if (!empty($row['token_hash']) && password_verify($code, $row['token_hash'])) {
            $matches = true;
            vlog("password_verify SUCCESS for token id={$row['id']}");
        } else {
            vlog("password_verify FAILED for token id={$row['id']}");
        }

        if ($matches) {
            // mark used and activate user in a transaction
            $mysqli->begin_transaction();
            
            $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ? LIMIT 1");
            $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $upd->bind_param('ssi', $v_ip, $v_ua, $row['id']);
            $upd->execute();
            $upd->close();

            $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1");
            $u->bind_param('i', $row['user_id']);
            $u->execute();
            $u->close();

            $mysqli->commit();
            vlog("verified jti={$jti} user_id={$row['user_id']}");
            reply(['success'=>true,'message'=>'verified','user_id'=>(int)$row['user_id']]);
        } else {
            // increment attempts
            $attempts = (int)$row['attempts'] + 1;
            $upd = $mysqli->prepare("UPDATE verification_tokens SET attempts = ? WHERE id = ? LIMIT 1");
            $upd->bind_param('ii', $attempts, $row['id']);
            $upd->execute();
            $upd->close();
            
            vlog("invalid_code jti={$jti} attempts={$attempts}");
            
            if ($attempts >= 5) {
                $blk = $mysqli->prepare("UPDATE verification_tokens SET used = 1 WHERE id = ? LIMIT 1");
                $blk->bind_param('i', $row['id']);
                $blk->execute();
                $blk->close();
                vlog("token_blocked_too_many_attempts id={$row['id']}");
                reply(['success'=>false,'message'=>'too_many_attempts','attempts'=>$attempts],429);
            }
            
            reply(['success'=>false,'message'=>'invalid_code','attempts'=>$attempts],401);
        }
    } else {
        // lookup active tokens for user_id
        // Use UTC comparison: current UTC time string
        $utc_now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT id,jti,user_id,token_hash,used,attempts,expires_at,expires_at_local,user_tz FROM verification_tokens WHERE user_id = ? AND used = 0 AND (expires_at IS NULL OR expires_at > ?) ORDER BY created_at DESC LIMIT 10";
        $st = $mysqli->prepare($sql);
        if (!$st) { 
            vlog("prepare_failed user_id lookup: " . $mysqli->error . " | sql: $sql"); 
            reply(['success'=>false,'message'=>'db_prepare_failed'],500); 
        }
        $st->bind_param('is', $user_id, $utc_now);
        $st->execute();
        $res = $st->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();

        vlog("Found " . count($rows) . " active tokens for user_id={$user_id}, utc_now={$utc_now}");

        if (empty($rows)) {
            // for debugging include recent tokens for this user if debug flag set
            $resp = ['success'=>false,'message'=>'no_active_tokens','total_tokens'=>0];
            
            if ($debug) {
                $st2 = $mysqli->prepare("SELECT id,jti,used,attempts,expires_at,expires_at_local,user_tz,created_at FROM verification_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
                if ($st2) {
                    $st2->bind_param('i',$user_id);
                    $st2->execute();
                    $res2 = $st2->get_result();
                    $resp['recent_tokens'] = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
                    $st2->close();
                }
                $resp['debug_utc_now'] = $utc_now;
                $resp['server_time'] = date('Y-m-d H:i:s');
                $resp['server_time_utc'] = gmdate('Y-m-d H:i:s');
            }
            
            vlog("no_active_tokens user_id={$user_id} debug=" . ($debug ? '1' : '0'));
            reply($resp, 404);
        }

        // try each token (most recently created first)
        foreach ($rows as $index => $row) {
            vlog("Trying token {$index}: jti={$row['jti']}, expires_at={$row['expires_at']}, attempts={$row['attempts']}");
            
            // Debug: log the hash and code attempt
            vlog("Verifying code for token {$index}: code='{$code}', token_hash=" . substr($row['token_hash'], 0, 20) . "...");
            
            if (!empty($row['token_hash']) && password_verify($code, $row['token_hash'])) {
                // success for this token
                vlog("password_verify SUCCESS for token id={$row['id']}");
                
                $mysqli->begin_transaction();
                
                $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ? LIMIT 1");
                $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                $upd->bind_param('ssi', $v_ip, $v_ua, $row['id']);
                $upd->execute();
                $upd->close();

                $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1");
                $u->bind_param('i', $row['user_id']);
                $u->execute();
                $u->close();

                $mysqli->commit();
                vlog("verified user_id={$row['user_id']} jti={$row['jti']}");
                reply(['success'=>true,'message'=>'verified','user_id'=>(int)$row['user_id'],'jti'=>$row['jti']]);
            } else {
                vlog("password_verify FAILED for token id={$row['id']}");
            }
        }

        // no matching token found; increment attempts for the most recent token (if exists)
        $first = $rows[0];
        $attempts = (int)$first['attempts'] + 1;
        
        $upd = $mysqli->prepare("UPDATE verification_tokens SET attempts = ? WHERE id = ? LIMIT 1");
        $upd->bind_param('ii', $attempts, $first['id']);
        $upd->execute();
        $upd->close();
        
        vlog("invalid_code user_id={$user_id} attempts={$attempts} on_token_id={$first['id']}");
        
        if ($attempts >= 5) {
            $blk = $mysqli->prepare("UPDATE verification_tokens SET used = 1 WHERE id = ? LIMIT 1");
            $blk->bind_param('i', $first['id']);
            $blk->execute();
            $blk->close();
            vlog("token_blocked_too_many_attempts id={$first['id']}");
            reply(['success'=>false,'message'=>'too_many_attempts','attempts'=>$attempts],429);
        }
        
        reply(['success'=>false,'message'=>'invalid_code','attempts'=>$attempts],401);
    }

} catch (Throwable $e) {
    vlog("exception: " . $e->getMessage());
    @file_put_contents($logDir . '/verify_code_stack.log', date('c') . " " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND | LOCK_EX);
    reply(['success'=>false,'message'=>'exception','details'=>$e->getMessage()],500);
}
?>