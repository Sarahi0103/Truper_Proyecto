<?php
/**
 * ApiResponse Class
 * Estandariza las respuestas de la API
 */

class ApiResponse {
    /**
     * Respuesta exitosa
     */
    public static function success($data = null, $message = 'OK', $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Respuesta de error
     */
    public static function error($message = 'Error', $code = 400, $errors = null) {
        http_response_code($code);
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => time()
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Respuesta de validación
     */
    public static function validationError($errors, $message = 'Validation failed') {
        return self::error($message, 422, $errors);
    }

    /**
     * Respuesta de no encontrado
     */
    public static function notFound($message = 'Resource not found') {
        return self::error($message, 404);
    }

    /**
     * Respuesta de no autorizado
     */
    public static function unauthorized($message = 'Unauthorized') {
        return self::error($message, 401);
    }

    /**
     * Respuesta de prohibido
     */
    public static function forbidden($message = 'Forbidden') {
        return self::error($message, 403);
    }

    /**
     * Respuesta de error del servidor
     */
    public static function serverError($message = 'Internal server error') {
        return self::error($message, 500);
    }

    /**
     * Respuesta paginada
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'OK') {
        http_response_code(200);
        return json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ],
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Respuesta de creado
     */
    public static function created($data = null, $message = 'Resource created') {
        return self::success($data, $message, 201);
    }

    /**
     * Respuesta de no contenido
     */
    public static function noContent() {
        http_response_code(204);
        return '';
    }

    /**
     * Envía la respuesta JSON con headers apropiados
     */
    public static function send($response) {
        header('Content-Type: application/json; charset=UTF-8');
        echo $response;
        exit;
    }

    /**
     * Envía respuesta exitosa
     */
    public static function sendSuccess($data = null, $message = 'OK', $code = 200) {
        self::send(self::success($data, $message, $code));
    }

    /**
     * Envía respuesta de error
     */
    public static function sendError($message = 'Error', $code = 400, $errors = null) {
        self::send(self::error($message, $code, $errors));
    }

    /**
     * Envía respuesta de validación
     */
    public static function sendValidationError($errors, $message = 'Validation failed') {
        self::send(self::validationError($errors, $message));
    }

    /**
     * Envía respuesta de no encontrado
     */
    public static function sendNotFound($message = 'Resource not found') {
        self::send(self::notFound($message));
    }

    /**
     * Envía respuesta de no autorizado
     */
    public static function sendUnauthorized($message = 'Unauthorized') {
        self::send(self::unauthorized($message));
    }

    /**
     * Envía respuesta de prohibido
     */
    public static function sendForbidden($message = 'Forbidden') {
        self::send(self::forbidden($message));
    }

    /**
     * Envía respuesta de error del servidor
     */
    public static function sendServerError($message = 'Internal server error') {
        self::send(self::serverError($message));
    }

    /**
     * Envía respuesta paginada
     */
    public static function sendPaginated($data, $total, $page, $perPage, $message = 'OK') {
        self::send(self::paginated($data, $total, $page, $perPage, $message));
    }

    /**
     * Envía respuesta de creado
     */
    public static function sendCreated($data = null, $message = 'Resource created') {
        self::send(self::created($data, $message));
    }

    /**
     * Envía respuesta de no contenido
     */
    public static function sendNoContent() {
        self::send(self::noContent());
    }
}
