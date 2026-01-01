<?php
// helper: ensure_document_type_id($mysqli, $provided_id)
// Returns a valid document_type id (int). If $provided_id exists in document_types returns it.
// Otherwise returns id of 'other_supporting' if exists, else attempts to create it and return its id.
// Returns 0 only if creation/checking failed.
function ensure_document_type_id($mysqli, $provided_id = null) {
    // 1) if provided numeric id — verify existence
    if ($provided_id && is_numeric($provided_id)) {
        $q = $mysqli->prepare('SELECT id FROM document_types WHERE id = ? LIMIT 1');
        if ($q) {
            $q->bind_param('i', $provided_id);
            $q->execute();
            $q->bind_result($found);
            if ($q->fetch()) { $q->close(); return (int)$found; }
            $q->close();
        }
    }

    // 2) try find 'other_supporting'
    $r = $mysqli->prepare("SELECT id FROM document_types WHERE key_name = 'other_supporting' LIMIT 1");
    if ($r) {
        $r->execute();
        $r->bind_result($fid);
        if ($r->fetch()) { $r->close(); return (int)$fid; }
        $r->close();
    }

    // 3) create fallback type (best-effort)
    $ins = $mysqli->prepare("INSERT INTO document_types (key_name, display_name, required_for, allow_multiple, allowed_mimetypes, max_size_bytes, created_at) VALUES (?, ?, 'all', 1, ?, ?, NOW())");
    if ($ins) {
        $key = 'other_supporting';
        $display = 'Other Supporting Documents';
        $mimes = 'application/pdf,image/jpeg,image/png';
        $max = 10485760; // 10 MB
        // bind: key, display, allowed_mimetypes, max_size_bytes
        // Note: some PHP/Mysqli setups require types exactly; use 'sssi' for s,s,s,i
        if (@$ins->bind_param('sssi', $key, $display, $mimes, $max)) {
            if (@$ins->execute()) {
                $newid = $mysqli->insert_id;
                $ins->close();
                return (int)$newid;
            }
        }
        $ins->close();
    }

    // failed to ensure
    return 0;
}
?>