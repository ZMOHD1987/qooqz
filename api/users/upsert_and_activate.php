<?php
// htdocs/api/register_upsert_and_activate.php
// Unified endpoint to:
//  - register (create) a user (and optional store) in a single DB transaction,
//  - generate & send a short numeric code via WhatsApp (channel "code"),
//  - verify a submitted code and activate the user and their store.
//
// Usage (JSON POST):
//  - action: "register" | "verify"
//  - phone: E.164 phone (required for both)
//  - username: optional
//  - store_name: optional (will create a store record linked to user; store.is_active remains 0 until verification)
//  - ttl: optional code lifetime seconds (default 900)
//  - code: when action="verify" provide the 6-digit code received via WhatsApp
//
// Responses:
//  - action=register -> { ok:true, action:'register', user_id, jti, id, code (only in dev/testing), send_result }
//  - action=verify   -> { success:true, message:'verified', user_id, jti, activated_store_id }
// Security note:
//  - In production you SHOULD NOT return the numeric code in the response. For development/testing the code is returned
//    to ease single-page flows. Remove / omit 'code' from responses in production.

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/whatsapp_service.php';

$raw = file_get_contents('php://input');
$input = $_POST;
if ($raw && empty($input)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) $input = $j;
    } else {
        parse_str($raw, $p);
        if (is_array($p)) $input = array_merge($input, $p);
    }
}

$action = isset($input['action']) ? strtolower(trim((string)$input['action'])) : 'register';
$phone = isset($input['phone']) ? trim((string)$input['phone']) : null;
$username = isset($input['username']) ? trim((string)$input['username']) : null;
$store_name = isset($input['store_name']) ? trim((string)$input['store_name']) : null;
$ttl = isset($input['ttl']) ? max(60, (int)$input['ttl']) : 900;
$code_submitted = isset($input['code']) ? trim((string)$input['code']) : null;

if (!$phone || !preg_match('/^\+\d{6,15}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'phone_required_or_invalid']);
    exit;
}

$mysqli = @connectDB();
if (!$mysqli) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_connect_failed']); exit; }

try {
    if ($action === 'register') {
        // Begin transaction: create or find user, create store (inactive) and create code row, send WhatsApp
        $mysqli->begin_transaction();

        // find or create user
        $user_id = null;
        $s = $mysqli->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        if ($s) {
            $s->bind_param('s', $phone);
            $s->execute();
            $r = $s->get_result();
            if ($row = $r->fetch_assoc()) $user_id = (int)$row['id'];
            $s->close();
        }
        if (!$user_id) {
            $uname = $username ?: ('user_' . substr(md5($phone . microtime(true)), 0, 8));
            $ins = $mysqli->prepare("INSERT INTO users (username, phone, is_active, created_at) VALUES (?, ?, 0, NOW())");
            if (!$ins) throw new RuntimeException('user_insert_prepare_failed: '.$mysqli->error);
            $ins->bind_param('ss', $uname, $phone);
            if (!$ins->execute()) throw new RuntimeException('user_insert_exec_failed: '.$ins->error);
            $user_id = $ins->insert_id;
            $ins->close();
        }

        // create a store record (optional) — store starts inactive until verification
        $store_id = null;
        if ($store_name) {
            $s2 = $mysqli->prepare("INSERT INTO stores (owner_user_id, name, slug, email, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if (!$s2) throw new RuntimeException('store_insert_prepare_failed: '.$mysqli->error);
            // simple slug
            $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower(substr($store_name,0,60)));
            $email = null;
            $s2->bind_param('issss', $user_id, $store_name, $slug, $email, $phone);
            if (!$s2->execute()) throw new RuntimeException('store_insert_exec_failed: '.$s2->error);
            $store_id = $s2->insert_id;
            $s2->close();
        }

        // generate 6-digit code
        try { $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); } catch (Throwable $e) { $code = (string)mt_rand(100000,999999); }
        $jti = bin2hex(random_bytes(12));
        $iat = time();
        $exp = $iat + $ttl;
        $issued_at_dt = date('Y-m-d H:i:s', $iat);
        $expires_at_dt = date('Y-m-d H:i:s', $exp);
        $token_hash = password_hash($code, PASSWORD_DEFAULT);
        $ip_raw = $_SERVER['REMOTE_ADDR'] ?? null;
        $ip_bin = null;
        if ($ip_raw) {
            $packed = @inet_pton($ip_raw);
            if ($packed !== false) $ip_bin = $packed;
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $origin = 'whatsapp-register';

        // Insert verification_tokens row
        $stmt = $mysqli->prepare("INSERT INTO verification_tokens (jti, user_id, channel, token_hash, expires_at, used, attempts, ip, created_at, phone, issued_at, origin, issuer_user_agent, issuer_ip) VALUES (?, ?, 'code', ?, ?, 0, 0, ?, NOW(), ?, ?, ?, ?, ?)");
        if (!$stmt) throw new RuntimeException('vt_prepare_failed: '.$mysqli->error);
        $params = [$jti, $user_id, $token_hash, $expires_at_dt, $ip_bin, $phone, $issued_at_dt, $origin, $ua, $ip_raw];
        $types = ''; foreach ($params as $p) $types .= is_int($p) ? 'i' : 's';
        $bind = [$types];
        foreach ($params as &$pv) $bind[] = &$pv;
        if (!call_user_func_array([$stmt,'bind_param'],$bind)) throw new RuntimeException('vt_bind_failed: '.$stmt->error);
        if (!$stmt->execute()) throw new RuntimeException('vt_exec_failed: '.$stmt->error);
        $vt_id = $stmt->insert_id;
        $stmt->close();

        // send via WhatsApp (best-effort)
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = $scheme.'://'.$host.'/verify_token.html?token='.rawurlencode($code);
        $message = "كود التحقق: {$code}\nصالح لمدة " . ((int)$ttl/60) . " دقيقة.\nأو اضغط للتحقق: {$link}";
        $send_result = send_whatsapp_text($phone, $message);

        $mysqli->commit();

        // NOTE: In production remove 'code' from responses so the code is only delivered via WhatsApp.
        echo json_encode([
            'ok' => true,
            'action' => 'register',
            'user_id' => $user_id,
            'store_id' => $store_id,
            'jti' => $jti,
            'vt_id' => $vt_id,
            'code' => $code, // remove in prod
            'send_result' => $send_result
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    } elseif ($action === 'verify') {
        // Verify code for phone (or jti). If matched -> mark token used, activate user and their store
        if (!$code_submitted) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'code required']); exit; }

        // Find matching token rows: prefer jti if provided, otherwise search recent unused codes by phone
        if (isset($input['jti']) && $input['jti']) {
            $q = $mysqli->prepare("SELECT id,jti,user_id,token_hash,expires_at,used FROM verification_tokens WHERE jti = ? LIMIT 1");
            $q->bind_param('s', $input['jti']);
        } else {
            $q = $mysqli->prepare("SELECT id,jti,user_id,token_hash,expires_at,used FROM verification_tokens WHERE phone = ? AND used = 0 ORDER BY id DESC LIMIT 10");
            $q->bind_param('s', $phone);
        }
        $q->execute();
        $res = $q->get_result();
        $matched = false;
        $found = null;
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) continue;
            if (!empty($row['token_hash']) && password_verify($code_submitted, $row['token_hash'])) {
                $matched = true;
                $found = $row;
                break;
            }
        }
        $q->close();

        if (!$matched) {
            http_response_code(401);
            echo json_encode(['success'=>false,'message'=>'invalid_code']);
            exit;
        }

        // Lock / mark used and activate user + their stores (in transaction)
        $mysqli->begin_transaction();
        $v_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $v_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $upd = $mysqli->prepare("UPDATE verification_tokens SET used = 1, used_at = NOW(), verifier_ip = ?, verifier_user_agent = ? WHERE id = ? LIMIT 1");
        if ($upd) { $upd->bind_param('ssi', $v_ip, $v_ua, $found['id']); $upd->execute(); $upd->close(); }

        // activate user
        $uid = (int)$found['user_id'];
        $u = $mysqli->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($u) { $u->bind_param('i', $uid); $u->execute(); $u->close(); }

        // activate stores owned by this user (if any). Alternatively you might selectively activate only the store created earlier.
        $s = $mysqli->prepare("UPDATE stores SET is_active = 1, updated_at = NOW() WHERE owner_user_id = ? AND is_active = 0");
        if ($s) { $s->bind_param('i', $uid); $s->execute(); $s->close(); }

        $mysqli->commit();

        echo json_encode(['success'=>true,'message'=>'verified','jti'=>$found['jti'],'user_id'=>$uid]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'unknown_action']);
        exit;
    }
} catch (Throwable $e) {
    if ($mysqli && $mysqli->errno) { try { $mysqli->rollback(); } catch (Throwable $_) {} }
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'exception','details'=>$e->getMessage()]);
    exit;
}
?>