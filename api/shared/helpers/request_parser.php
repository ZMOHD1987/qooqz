<?php
declare(strict_types=1);

/**
 * Parse request body data for all HTTP methods.
 * PHP only populates $_POST for POST requests.
 * This handles JSON, URL-encoded, and multipart/form-data for PUT/DELETE too.
 *
 * @return array Parsed data
 */
function parse_request_data(): array {
    // Try JSON first
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (is_array($data) && !empty($data)) {
        return $data;
    }

    // For POST, use $_POST
    if (!empty($_POST)) {
        return $_POST;
    }

    // For PUT/DELETE with form data
    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['PUT', 'DELETE', 'PATCH'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($rawBody, $data);
            return $data;
        }

        if (strpos($contentType, 'multipart/form-data') !== false) {
            $data = [];
            if (preg_match('/boundary=(.*)$/i', $contentType, $matches)) {
                $parts = preg_split('/-+' . preg_quote($matches[1], '/') . '/', $rawBody);
                foreach ($parts as $part) {
                    if (empty(trim($part)) || trim($part) === '--') continue;
                    if (preg_match('/name="([^"]+)"/', $part, $nameMatch)) {
                        $value = trim(substr($part, strpos($part, "\r\n\r\n") + 4));
                        $value = rtrim($value, "\r\n");
                        $data[$nameMatch[1]] = $value;
                    }
                }
            }
            return $data;
        }
    }

    return [];
}