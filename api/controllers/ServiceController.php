<?php
// htdocs/api/controllers/ServiceController.php
// Controller لإدارة الخدمات (CRUD، تسعير، حجوزات، التوفر، مراجعات، ربط تصنيفات)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/upload.php';
require_once __DIR__ . '/../helpers/notification.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

class ServiceController
{
    /**
     * إنشاء خدمة جديدة (مزود الخدمة أو Admin)
     * POST /api/services
     */
    public static function create()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        // إذا لم يكن admin، يجب أن يكون مزود (provider/vendor)
        if (!AuthMiddleware::isAdmin() && $user['user_type'] !== USER_TYPE_VENDOR && $user['user_type'] !== USER_TYPE_SUPPORT) {
            Response::forbidden('Only providers or admins can create services');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rules = [
            'title' => 'required|string|min:3|max:255',
            'slug' => 'optional|string|min:3|max:255|unique:services,slug',
            'short_description' => 'optional|string|max:1000',
            'description' => 'optional|string',
            'duration_minutes' => 'optional|integer|min:1',
            'price' => 'required|numeric',
            'currency' => 'optional|string',
            'capacity' => 'optional|integer|min:1',
            'buffer_time_minutes' => 'optional|integer|min:0',
            'is_active' => 'optional|boolean',
            'categories' => 'optional|array'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $serviceModel = new Service();

        // provider assignment: admin may pass provider_id
        if (AuthMiddleware::isAdmin() && !empty($input['provider_id'])) {
            $providerId = (int)$input['provider_id'];
        } else {
            // find provider by user (vendor)
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) {
                Response::error('Provider account not found for this user', 400);
            }
            $providerId = $vendor['user_id']; // services.provider_id references users.id
        }

        // ensure slug
        $slug = $validated['slug'] ?? Utils::createSlug($validated['title']);
        if (!$serviceModel->isSlugUnique($slug)) {
            $slug .= '-' . substr(Utils::generateUUID(), 0, 6);
        }

        $createData = [
            'provider_id' => $providerId,
            'title' => $validated['title'],
            'slug' => $slug,
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'price' => (float)$validated['price'],
            'currency' => $validated['currency'] ?? DEFAULT_CURRENCY,
            'is_active' => isset($validated['is_active']) ? (int)$validated['is_active'] : 1,
            'capacity' => $validated['capacity'] ?? 1,
            'buffer_time_minutes' => $validated['buffer_time_minutes'] ?? 0
        ];

        $newService = $serviceModel->create($createData);

        if (!$newService) {
            Response::error('Failed to create service', 500);
        }

        // attach categories if provided
        if (!empty($validated['categories']) && is_array($validated['categories'])) {
            foreach ($validated['categories'] as $idx => $catId) {
                $serviceModel->attachCategory($newService['id'], (int)$catId, $idx === 0);
            }
        }

        Response::created(['service' => $serviceModel->findById($newService['id'])], 'Service created successfully');
    }

    /**
     * تحديث خدمة
     * PUT /api/services/{id}
     */
    public static function update($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $serviceModel = new Service();
        $existing = $serviceModel->findById($id);
        if (!$existing) Response::error('Service not found', 404);

        // ownership: providers can update their services
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            // service.provider_id stores users.id (provider user id)
            if (!$vendor || $existing['provider_id'] != $user['id']) {
                Response::forbidden('You do not have permission to update this service');
            }
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rules = [
            'title' => 'optional|string|min:3|max:255',
            'slug' => "optional|string|min:3|max:255|unique:services,slug,{$id}",
            'short_description' => 'optional|string|max:1000',
            'description' => 'optional|string',
            'duration_minutes' => 'optional|integer|min:1',
            'price' => 'optional|numeric',
            'currency' => 'optional|string',
            'capacity' => 'optional|integer|min:1',
            'buffer_time_minutes' => 'optional|integer|min:0',
            'is_active' => 'optional|boolean',
            'categories' => 'optional|array'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $ok = $serviceModel->update($id, $validated);
        if (!$ok) Response::error('Failed to update service', 500);

        // update categories if provided
        if (isset($validated['categories']) && is_array($validated['categories'])) {
            // detach existing then attach provided
            $mysqli = connectDB();
            $stmt = $mysqli->prepare("DELETE FROM service_categories WHERE service_id = ?");
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }

            foreach ($validated['categories'] as $idx => $catId) {
                $serviceModel->attachCategory($id, (int)$catId, $idx === 0);
            }
        }

        Response::success(['service' => $serviceModel->findById($id)], 'Service updated successfully');
    }

    /**
     * حذف خدمة (soft/hard)
     * DELETE /api/services/{id}
     */
    public static function delete($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $serviceModel = new Service();
        $existing = $serviceModel->findById($id);
        if (!$existing) Response::error('Service not found', 404);

        // ownership check
        if (!AuthMiddleware::isAdmin() && $existing['provider_id'] != $user['id']) {
            Response::forbidden('You do not have permission to delete this service');
        }

        $hard = isset($_GET['hard']) && $_GET['hard'] == '1';
        $ok = $serviceModel->delete($id, $hard);
        if ($ok) {
            Response::success(null, $hard ? 'Service permanently deleted' : 'Service soft deleted');
        }

        Response::error('Failed to delete service', 500);
    }

    /**
     * جلب خدمة (by id or slug)
     * GET /api/services/{id_or_slug}
     */
    public static function show($idOrSlug = null)
    {
        if (!$idOrSlug) Response::validationError(['id' => ['Service id or slug is required']]);

        $serviceModel = new Service();
        $service = is_numeric($idOrSlug) ? $serviceModel->findById((int)$idOrSlug) : $serviceModel->findBySlug($idOrSlug);

        if (!$service) Response::error('Service not found', 404);

        Response::success($service);
    }

    /**
     * قائمة الخدمات مع فلترة
     * GET /api/services
     */
    public static function index()
    {
        $q = $_GET;
        $page = isset($q['page']) ? (int)$q['page'] : 1;
        $perPage = isset($q['per_page']) ? (int)$q['per_page'] : 20;

        $filters = [];
        if (!empty($q['provider_id'])) $filters['provider_id'] = (int)$q['provider_id'];
        if (isset($q['is_active'])) $filters['is_active'] = (int)$q['is_active'];
        if (!empty($q['category_id'])) $filters['category_id'] = (int)$q['category_id'];
        if (!empty($q['min_price'])) $filters['min_price'] = (float)$q['min_price'];
        if (!empty($q['max_price'])) $filters['max_price'] = (float)$q['max_price'];
        if (!empty($q['search'])) $filters['search'] = $q['search'];

        $serviceModel = new Service();
        $result = $serviceModel->getAll($filters, $page, $perPage);

        Response::success($result);
    }

    /**
     * التحقق من توفر خدمة لوقت محدد
     * GET /api/services/{id}/availability?start_at=Y-m-d H:i:s
     */
    public static function availability($id = null)
    {
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $startAt = $_GET['start_at'] ?? null;
        if (!$startAt) Response::validationError(['start_at' => ['start_at is required']]);

        $serviceModel = new Service();
        $ok = $serviceModel->isAvailable($id, $startAt);

        Response::success(['available' => (bool)$ok]);
    }

    /**
     * حجز خدمة (Booking)
     * POST /api/services/book
     * body: service_id, start_at (Y-m-d H:i:s), attendees, notes
     */
    public static function book()
    {
        $user = AuthMiddleware::authenticateOptional();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rules = [
            'service_id' => 'required|integer|exists:services,id',
            'start_at' => 'required|date',
            'attendees' => 'optional|integer|min:1'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $serviceModel = new Service();

        $bookingData = [
            'service_id' => (int)$validated['service_id'],
            'user_id' => $user['id'] ?? null,
            'start_at' => $validated['start_at'],
            'attendees' => $validated['attendees'] ?? 1,
            'notes' => $input['notes'] ?? null
        ];

        $res = $serviceModel->book($bookingData);

        if (isset($res['success']) && $res['success']) {
            // Notify provider and user
            $service = $serviceModel->findById($bookingData['service_id']);
            if ($service) {
                Notification::send($service['provider_id'], 'service_booking', 'New Booking', "A new booking (#{$res['booking_id']}) was created", [], ['database', 'email']);
                if (!empty($service['provider_email'])) {
                    // optional email helper
                }
            }

            Response::created(['booking_id' => $res['booking_id']], $res['message'] ?? 'Booking created');
        }

        Response::error($res['message'] ?? 'Failed to create booking', 400);
    }

    /**
     * جلب الحجوزات لخدمة (provider only or admin)
     * GET /api/services/{id}/bookings
     */
    public static function bookings($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $serviceModel = new Service();
        $service = $serviceModel->findById($id);
        if (!$service) Response::error('Service not found', 404);

        // Authorization: provider or admin
        if (!AuthMiddleware::isAdmin() && $service['provider_id'] != $user['id']) {
            Response::forbidden('You do not have permission to view bookings for this service');
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

        $result = $serviceModel->getBookings($id, $page, $perPage);
        Response::success($result);
    }

    /**
     * إلغاء حجز (provider or admin or owner)
     * POST /api/services/bookings/{booking_id}/cancel
     */
    public static function cancelBooking($bookingId = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$bookingId) Response::validationError(['booking_id' => ['Booking id is required']]);
        $bookingId = (int)$bookingId;

        $serviceModel = new Service();

        // جلب الحجز للتحقق من الصلاحيات
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("SELECT sb.*, s.provider_id FROM service_bookings sb INNER JOIN services s ON sb.service_id = s.id WHERE sb.id = ? LIMIT 1");
        if (!$stmt) Response::error('DB error', 500);
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); Response::error('Booking not found', 404); }
        $booking = $res->fetch_assoc();
        $stmt->close();

        // Authorization: admin, provider (service owner), or booking owner (user)
        if (!AuthMiddleware::isAdmin() && $booking['provider_id'] != $user['id'] && $booking['user_id'] != $user['id']) {
            Response::forbidden('You do not have permission to cancel this booking');
        }

        $reason = $_POST['reason'] ?? null;
        $ok = $serviceModel->cancelBooking($bookingId, $reason);
        if ($ok) {
            // notify parties
            Notification::send($booking['user_id'], 'booking_cancelled', 'Booking Cancelled', "Your booking #{$bookingId} was cancelled", [], ['database', 'email']);
            Response::success(null, 'Booking cancelled');
        }

        Response::error('Failed to cancel booking', 500);
    }

    /**
     * جلب مراجعات خدمة
     * GET /api/services/{id}/reviews
     */
    public static function reviews($id = null)
    {
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        $serviceModel = new Service();
        $res = $serviceModel->getReviews($id, $page, $perPage);

        Response::success($res);
    }

    /**
     * إضافة مراجعة
     * POST /api/services/{id}/reviews
     * body: rating, comment
     */
    public static function addReview($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rules = [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'optional|string|max:2000'
        ];
        $validated = Validator::make($input, $rules)->validated();

        $serviceModel = new Service();
        $ok = $serviceModel->addReview($id, $user['id'], (int)$validated['rating'], $validated['comment'] ?? null);

        if ($ok) {
            Response::created(null, 'Review added successfully');
        }

        Response::error('Failed to add review', 500);
    }

    /**
     * حفظ/تحديث تسعير خدمة (provider or admin)
     * POST /api/services/{id}/pricing
     */
    public static function savePricing($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Service id is required']]);
        $id = (int)$id;

        $serviceModel = new Service();
        $service = $serviceModel->findById($id);
        if (!$service) Response::error('Service not found', 404);

        if (!AuthMiddleware::isAdmin() && $service['provider_id'] != $user['id']) {
            Response::forbidden('You do not have permission to update pricing for this service');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rules = [
            'price' => 'required|numeric',
            'currency' => 'optional|string',
            'duration_minutes' => 'optional|integer'
        ];
        $validated = Validator::make($input, $rules)->validated();

        $ok = $serviceModel->savePricing($id, [
            'price' => (float)$validated['price'],
            'currency' => $validated['currency'] ?? DEFAULT_CURRENCY,
            'duration_minutes' => $validated['duration_minutes'] ?? null
        ]);

        if ($ok) {
            Response::success($serviceModel->getPricing($id), 'Pricing saved');
        }

        Response::error('Failed to save pricing', 500);
    }

    /**
     * إحصائيات الخدمات (Admin or provider)
     * GET /api/services/{id}/stats  or /api/services/stats
     */
    public static function stats($id = null)
    {
        $user = AuthMiddleware::authenticateOptional();

        $serviceModel = new Service();

        if ($id) {
            $id = (int)$id;
            $service = $serviceModel->findById($id);
            if (!$service) Response::error('Service not found', 404);

            if (!AuthMiddleware::isAdmin() && $user && $service['provider_id'] != $user['id']) {
                Response::forbidden('You do not have permission to view these stats');
            }

            // basic stats per service
            $sql = "SELECT COUNT(*) as total_bookings, SUM(total) as total_revenue, AVG(rating) as avg_rating
                    FROM service_bookings sb
                    LEFT JOIN service_reviews sr ON sr.service_id = sb.service_id
                    WHERE sb.service_id = ?";
            $mysqli = connectDB();
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $stats = $res->fetch_assoc();
            $stmt->close();

            Response::success($stats);
        } else {
            // global stats (admin only)
            RoleMiddleware::canRead('reports');
            $stats = $serviceModel->getStatistics();
            Response::success($stats);
        }
    }
}

// Router examples (handled elsewhere)
// POST /api/services => ServiceController::create();
// GET /api/services => ServiceController::index();

?>