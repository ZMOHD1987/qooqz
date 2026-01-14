<?php
// api/helpers/Response.php
// Wrapper class for response functions to maintain backward compatibility

if (!class_exists('Response')) {
    class Response
    {
        public static function success($data = null, $message = 'OK', $httpCode = 200)
        {
            respond_success($data, $message, $httpCode);
        }

        public static function error($message = 'Error', $httpCode = 400, $errors = null, $errorCode = null)
        {
            respond_error($message, $httpCode, $errors, $errorCode);
        }

        public static function created($data = null, $message = 'Created successfully')
        {
            respond_created($data, $message);
        }

        public static function unauthorized($message = 'Unauthorized')
        {
            respond_error($message, 401, null, ERROR_CODE_AUTHENTICATION);
        }

        public static function forbidden($message = 'Forbidden')
        {
            respond_error($message, 403, null, ERROR_CODE_AUTHORIZATION);
        }

        public static function notFound($message = 'Resource not found')
        {
            respond_not_found($message);
        }

        public static function validationError($errors, $message = 'Validation failed')
        {
            respond_validation_error($errors, $message);
        }

        public static function serverError($message = 'Internal server error')
        {
            respond_server_error($message);
        }
    }
}
