<?php
// htdocs/api/services/ShippingService.php
// Service layer for Shipping - handles shipping methods, zones, rates, calculation, and carrier webhooks.
// Designed to be called by controllers; returns structured arrays and does not emit HTTP responses directly.

require_once __DIR__ . '/../helpers/response.php';

class ShippingService
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: connectDB();
    }

    //
    // CRUD: Shipping Methods
    //
    public function listMethods($filters = [], $page = 1, $perPage = 50)
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM shipping_methods WHERE 1=1";
        $params = [];
        $types = '';

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)$filters['is_active'];
            $types .= 'i';
        }

        $sql .= " ORDER BY sort_order ASC, id DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $perPage;
        $types .= 'ii';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $methods = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        // count
        $r = $this->db->query("SELECT COUNT(*) as cnt FROM shipping_methods");
        $total = 0;
        if ($r) { $row = $r->fetch_assoc(); $total = (int)$row['cnt']; $r->close(); }

        return ['data' => $methods, 'total' => $total];
    }

    public function createMethod(array $data)
    {
        $name = $this->db->real_escape_string($data['name'] ?? 'Unnamed');
        $provider = $this->db->real_escape_string($data['provider'] ?? null);
        $settings = isset($data['settings']) ? json_encode($data['settings']) : null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $sort = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

        $stmt = $this->db->prepare("INSERT INTO shipping_methods (name, provider, settings, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) return false;
        $stmt->bind_param('sssii', $name, $provider, $settings, $isActive, $sort);
        $ok = $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();
        return $ok ? $this->getMethodById($id) : false;
    }

    public function updateMethod($id, array $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['name'])) { $fields[] = "name = ?"; $params[] = $data['name']; $types .= 's'; }
        if (array_key_exists('provider', $data)) { $fields[] = "provider = ?"; $params[] = $data['provider']; $types .= 's'; }
        if (array_key_exists('settings', $data)) { $fields[] = "settings = ?"; $params[] = json_encode($data['settings']); $types .= 's'; }
        if (isset($data['is_active'])) { $fields[] = "is_active = ?"; $params[] = (int)$data['is_active']; $types .= 'i'; }
        if (isset($data['sort_order'])) { $fields[] = "sort_order = ?"; $params[] = (int)$data['sort_order']; $types .= 'i'; }

        if (empty($fields)) return $this->getMethodById($id);

        $sql = "UPDATE shipping_methods SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = (int)$id;
        $types .= 'i';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $this->getMethodById($id) : false;
    }

    public function deleteMethod($id)
    {
        $stmt = $this->db->prepare("DELETE FROM shipping_methods WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function getMethodById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM shipping_methods WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res && $res->num_rows ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && !empty($row['settings'])) $row['settings'] = json_decode($row['settings'], true);
        return $row;
    }

    //
    // Zones CRUD
    //
    public function listZones()
    {
        $res = $this->db->query("SELECT * FROM shipping_zones ORDER BY sort_order ASC, id DESC");
        $zones = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->close();
        foreach ($zones as &$z) {
            $z['regions'] = $this->getZoneRegions($z['id']);
        }
        return $zones;
    }

    public function createZone(array $data)
    {
        $name = $this->db->real_escape_string($data['name'] ?? 'Zone');
        $sort = (int)($data['sort_order'] ?? 0);
        $stmt = $this->db->prepare("INSERT INTO shipping_zones (name, sort_order, created_at) VALUES (?, ?, NOW())");
        if (!$stmt) return false;
        $stmt->bind_param('si', $name, $sort);
        $ok = $stmt->execute();
        $zoneId = $this->db->insert_id;
        $stmt->close();

        if ($ok && !empty($data['regions']) && is_array($data['regions'])) {
            $this->setZoneRegions($zoneId, $data['regions']);
        }

        return $ok ? $this->getZoneById($zoneId) : false;
    }

    public function updateZone($id, array $data)
    {
        $fields = [];
        $params = [];
        $types = '';
        if (isset($data['name'])) { $fields[] = "name = ?"; $params[] = $data['name']; $types .= 's'; }
        if (isset($data['sort_order'])) { $fields[] = "sort_order = ?"; $params[] = (int)$data['sort_order']; $types .= 'i'; }

        if (!empty($fields)) {
            $sql = "UPDATE shipping_zones SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $params[] = (int)$id; $types .= 'i';
            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (isset($data['regions']) && is_array($data['regions'])) {
            $this->setZoneRegions($id, $data['regions']);
        }

        return $this->getZoneById($id);
    }

    public function deleteZone($id)
    {
        // delete regions mapping first
        $stmt = $this->db->prepare("DELETE FROM shipping_zone_regions WHERE zone_id = ?");
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        $stmt2 = $this->db->prepare("DELETE FROM shipping_zones WHERE id = ?");
        if (!$stmt2) return false;
        $stmt2->bind_param('i', $id);
        $ok = $stmt2->execute();
        $stmt2->close();
        return (bool)$ok;
    }

    public function getZoneById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM shipping_zones WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $zone = $res && $res->num_rows ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($zone) $zone['regions'] = $this->getZoneRegions($id);
        return $zone;
    }

    protected function getZoneRegions($zoneId)
    {
        $stmt = $this->db->prepare("SELECT country, region, postal_code FROM shipping_zone_regions WHERE zone_id = ?");
        if (!$stmt) return [];
        $stmt->bind_param('i', $zoneId);
        $stmt->execute();
        $res = $stmt->get_result();
        $regions = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $regions;
    }

    protected function setZoneRegions($zoneId, array $regions)
    {
        // regions: array of ['country'=>'US', 'region'=>'CA', 'postal_code'=>'9*'] etc.
        $stmtDel = $this->db->prepare("DELETE FROM shipping_zone_regions WHERE zone_id = ?");
        if ($stmtDel) { $stmtDel->bind_param('i', $zoneId); $stmtDel->execute(); $stmtDel->close(); }

        $stmt = $this->db->prepare("INSERT INTO shipping_zone_regions (zone_id, country, region, postal_code) VALUES (?, ?, ?, ?)");
        if (!$stmt) return false;
        foreach ($regions as $r) {
            $country = $r['country'] ?? null;
            $region = $r['region'] ?? null;
            $postal = $r['postal_code'] ?? null;
            $stmt->bind_param('isss', $zoneId, $country, $region, $postal);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    //
    // Rates CRUD
    //
    public function listRates($filters = [])
    {
        $sql = "SELECT * FROM shipping_rates WHERE 1=1";
        if (isset($filters['zone_id'])) $sql .= " AND zone_id = " . (int)$filters['zone_id'];
        if (isset($filters['method_id'])) $sql .= " AND method_id = " . (int)$filters['method_id'];
        $sql .= " ORDER BY priority ASC, id DESC";
        $res = $this->db->query($sql);
        $rates = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($res) $res->close();
        return $rates;
    }

    public function createRate(array $data)
    {
        $zoneId = isset($data['zone_id']) ? (int)$data['zone_id'] : 0;
        $methodId = isset($data['method_id']) ? (int)$data['method_id'] : 0;
        $type = $this->db->real_escape_string($data['type'] ?? 'flat'); // flat, weight_based, price_based, table, free_over
        $amount = (float)($data['amount'] ?? 0.0);
        $conditions = isset($data['conditions']) ? json_encode($data['conditions']) : null;
        $priority = (int)($data['priority'] ?? 10);

        $stmt = $this->db->prepare("INSERT INTO shipping_rates (zone_id, method_id, type, amount, conditions, priority, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) return false;
        $stmt->bind_param('iisdsi', $zoneId, $methodId, $type, $amount, $conditions, $priority);
        $ok = $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();
        return $ok ? $this->getRateById($id) : false;
    }

    public function updateRate($id, array $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['zone_id'])) { $fields[] = "zone_id = ?"; $params[] = (int)$data['zone_id']; $types .= 'i'; }
        if (isset($data['method_id'])) { $fields[] = "method_id = ?"; $params[] = (int)$data['method_id']; $types .= 'i'; }
        if (isset($data['type'])) { $fields[] = "type = ?"; $params[] = $data['type']; $types .= 's'; }
        if (isset($data['amount'])) { $fields[] = "amount = ?"; $params[] = (float)$data['amount']; $types .= 'd'; }
        if (isset($data['conditions'])) { $fields[] = "conditions = ?"; $params[] = json_encode($data['conditions']); $types .= 's'; }
        if (isset($data['priority'])) { $fields[] = "priority = ?"; $params[] = (int)$data['priority']; $types .= 'i'; }

        if (empty($fields)) return $this->getRateById($id);

        $sql = "UPDATE shipping_rates SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = (int)$id; $types .= 'i';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $this->getRateById($id) : false;
    }

    public function deleteRate($id)
    {
        $stmt = $this->db->prepare("DELETE FROM shipping_rates WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function getRateById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM shipping_rates WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rate = $res && $res->num_rows ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($rate && !empty($rate['conditions'])) $rate['conditions'] = json_decode($rate['conditions'], true);
        return $rate;
    }

    //
    // Calculation / Matching
    //
    /**
     * Calculate shipping options for given items and destination.
     * $items: array of ['weight'=>float,'quantity'=>int,'price'=>float,'product_id'=>int] OR rely on carts/orders to pass totals
     * $destination: ['country'=>'US','region'=>'CA','postal_code'=>'90001']
     * $options: ['currency'=> 'USD', 'cart_total'=>float, 'total_weight'=>float]
     *
     * Returns array of available methods each with applied rate and breakdown:
     * [
     *  ['method' => methodRow, 'rate' => rateRow, 'price' => float, 'estimate' => '1-3 days']
     * ]
     */
    public function calculate(array $items, array $destination = [], array $options = [])
    {
        // Compute basic totals if not provided
        $cartTotal = isset($options['cart_total']) ? (float)$options['cart_total'] : 0.0;
        $totalWeight = isset($options['total_weight']) ? (float)$options['total_weight'] : 0.0;

        if ($totalWeight <= 0) {
            // try calculate from items
            foreach ($items as $it) {
                $w = (float)($it['weight'] ?? 0.0);
                $qty = (int)($it['quantity'] ?? 1);
                $totalWeight += $w * $qty;
                $cartTotal += (float)($it['price'] ?? 0.0) * $qty;
            }
        }

        // find candidate zones matching destination (ordered by specificity)
        $zones = $this->matchZonesByAddress($destination);

        $available = [];

        // get all active shipping methods
        $methodsRes = $this->db->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order ASC");
        $methods = $methodsRes ? $methodsRes->fetch_all(MYSQLI_ASSOC) : [];
        if ($methodsRes) $methodsRes->close();

        // For each method determine best applicable rate from zone(s)
        foreach ($methods as $method) {
            $methodId = (int)$method['id'];
            $matchedRate = null;

            // evaluate rates for each zone (zones already ordered by specificity)
            foreach ($zones as $zone) {
                $rates = $this->listRates(['zone_id' => $zone['id'], 'method_id' => $methodId]);
                if (empty($rates)) continue;

                // evaluate rates and pick cheapest valid one
                foreach ($rates as $rate) {
                    $price = $this->evaluateRatePrice($rate, $cartTotal, $totalWeight, $items, $destination);
                    if ($price === null) continue; // not applicable
                    $rate['calculated_price'] = $price;
                    $rate['applies_to_zone'] = $zone['id'];
                    if ($matchedRate === null || $price < $matchedRate['calculated_price']) {
                        $matchedRate = $rate;
                    }
                }

                if ($matchedRate) break; // we prefer rates in more specific zone
            }

            if ($matchedRate) {
                $estimate = $method['settings'] ? ($this->extractEstimateFromSettings($method['settings']) ?? null) : null;
                $available[] = [
                    'method' => $method,
                    'rate' => $matchedRate,
                    'price' => round((float)$matchedRate['calculated_price'], 2),
                    'estimate' => $estimate
                ];
            }
        }

        // sort by price asc
        usort($available, function($a, $b) { return $a['price'] <=> $b['price']; });

        return $available;
    }

    /**
     * Match shipping zones by address. Returns ordered list of zones (most specific first).
     * Destination keys: country, region, postal_code.
     */
    protected function matchZonesByAddress(array $dest)
    {
        $country = strtoupper($dest['country'] ?? '');
        $region = strtoupper($dest['region'] ?? '');
        $postal = strtoupper(str_replace(' ', '', $dest['postal_code'] ?? ''));

        // Strategy:
        // 1) exact postal_code match
        // 2) region match
        // 3) country match
        // 4) global zone (zone_id = 0 or catch-all)
        $zones = [];
        // postal exact
        if ($postal !== '') {
            $stmt = $this->db->prepare("SELECT z.* FROM shipping_zones z INNER JOIN shipping_zone_regions r ON r.zone_id = z.id WHERE REPLACE(UPPER(r.postal_code), ' ', '') = ? LIMIT 10");
            if ($stmt) {
                $stmt->bind_param('s', $postal);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) { while ($r = $res->fetch_assoc()) $zones[$r['id']] = $r; }
                $stmt->close();
            }
        }

        // region
        if ($region !== '') {
            $stmt = $this->db->prepare("SELECT z.* FROM shipping_zones z INNER JOIN shipping_zone_regions r ON r.zone_id = z.id WHERE UPPER(r.region) = ? LIMIT 10");
            if ($stmt) {
                $stmt->bind_param('s', $region);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) { while ($r = $res->fetch_assoc()) if (!isset($zones[$r['id']])) $zones[$r['id']] = $r; }
                $stmt->close();
            }
        }

        // country
        if ($country !== '') {
            $stmt = $this->db->prepare("SELECT z.* FROM shipping_zones z INNER JOIN shipping_zone_regions r ON r.zone_id = z.id WHERE UPPER(r.country) = ? LIMIT 20");
            if ($stmt) {
                $stmt->bind_param('s', $country);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) { while ($r = $res->fetch_assoc()) if (!isset($zones[$r['id']])) $zones[$r['id']] = $r; }
                $stmt->close();
            }
        }

        // global zone fallback: zones without regions (global applicability)
        $res2 = $this->db->query("SELECT * FROM shipping_zones z WHERE NOT EXISTS (SELECT 1 FROM shipping_zone_regions r WHERE r.zone_id = z.id) LIMIT 10");
        if ($res2) { while ($r = $res2->fetch_assoc()) if (!isset($zones[$r['id']])) $zones[$r['id']] = $r; $res2->close(); }

        // return ordered by added order (specific first)
        return array_values($zones);
    }

    /**
     * Evaluate a rate's price according to its type and conditions.
     * Returns float price or null if not applicable.
     */
    protected function evaluateRatePrice(array $rate, $cartTotal, $totalWeight, $items, $destination)
    {
        $type = $rate['type'] ?? 'flat';
        $amount = (float)$rate['amount'];
        $conditions = !empty($rate['conditions']) ? json_decode($rate['conditions'], true) : [];

        switch ($type) {
            case 'flat':
                // optional min/max cart total conditions
                if (isset($conditions['min_total']) && $cartTotal < (float)$conditions['min_total']) return null;
                if (isset($conditions['max_total']) && $cartTotal > (float)$conditions['max_total']) return null;
                return $amount;

            case 'weight_based':
                // conditions may contain brackets: [{max:1, price:5},{max:5, price:10},{else:20}]
                if (!is_array($conditions) || empty($conditions['brackets'])) return null;
                foreach ($conditions['brackets'] as $b) {
                    if (isset($b['max']) && $totalWeight <= (float)$b['max']) return (float)$b['price'];
                }
                return isset($conditions['default_price']) ? (float)$conditions['default_price'] : null;

            case 'price_based':
                // shipping based on cart total (e.g., free over X)
                if (isset($conditions['free_over']) && $cartTotal >= (float)$conditions['free_over']) return 0.0;
                if (isset($conditions['tiers']) && is_array($conditions['tiers'])) {
                    foreach ($conditions['tiers'] as $tier) {
                        if ($cartTotal <= (float)$tier['upto']) return (float)$tier['price'];
                    }
                }
                return $amount;

            case 'per_item':
                $qty = 0;
                foreach ($items as $it) $qty += (int)($it['quantity'] ?? 1);
                return $amount * $qty;

            case 'table':
                // arbitrary table lookup stored in conditions
                if (!empty($conditions['table']) && is_array($conditions['table'])) {
                    // try match by postal code, region etc.
                    foreach ($conditions['table'] as $row) {
                        if (!empty($row['postal_code']) && strtoupper(str_replace(' ', '', $row['postal_code'])) === strtoupper(str_replace(' ', '', $destination['postal_code'] ?? ''))) {
                            return (float)$row['price'];
                        }
                        if (!empty($row['region']) && strtoupper($row['region']) === strtoupper($destination['region'] ?? '')) {
                            return (float)$row['price'];
                        }
                        if (!empty($row['country']) && strtoupper($row['country']) === strtoupper($destination['country'] ?? '')) {
                            return (float)$row['price'];
                        }
                    }
                }
                return null;

            default:
                // unknown type -> treat as flat
                return $amount;
        }
    }

    protected function extractEstimateFromSettings($settingsJson)
    {
        $settings = is_array($settingsJson) ? $settingsJson : json_decode($settingsJson, true);
        if (!$settings) return null;
        return $settings['estimate'] ?? $settings['delivery_time'] ?? null;
    }

    //
    // Convenience: return available methods for a cart or address
    //
    public function availableMethods(array $cartOrItemsOrTotals, array $destination = [], array $options = [])
    {
        // Normalize input: if first param contains keys 'items' or 'total_weight' or 'cart_total', use accordingly.
        $items = $cartOrItemsOrTotals['items'] ?? $cartOrItemsOrTotals;
        $cartTotal = $cartOrItemsOrTotals['cart_total'] ?? ($options['cart_total'] ?? null);
        $totalWeight = $cartOrItemsOrTotals['total_weight'] ?? ($options['total_weight'] ?? null);

        $calcOptions = ['cart_total' => $cartTotal, 'total_weight' => $totalWeight, 'currency' => $options['currency'] ?? null];

        return $this->calculate($items, $destination, $calcOptions);
    }

    //
    // Webhook handler (e.g., carrier tracking updates)
    //
    public function handleWebhook($carrier = null, array $payload = [], array $headers = [])
    {
        // This is a placeholder handler. It should validate signature (carrier-specific) and update tracking records.
        // Expected payload may contain: shipment_id, order_number, tracking_number, status, delivered_at, events[]
        try {
            $shipmentRef = $payload['shipment_id'] ?? ($payload['tracking_number'] ?? null);
            if (!$shipmentRef) return ['success' => false, 'message' => 'No shipment reference found'];

            // store or update tracking table
            $shipmentId = null;
            // attempt find existing by tracking number or shipment id
            if (!empty($payload['tracking_number'])) {
                $t = $this->db->real_escape_string($payload['tracking_number']);
                $res = $this->db->query("SELECT * FROM shipments WHERE tracking_number = '{$t}' LIMIT 1");
                if ($res && $res->num_rows) {
                    $row = $res->fetch_assoc();
                    $shipmentId = $row['id'];
                    $res->close();
                }
            }

            if (!$shipmentId && !empty($payload['shipment_id'])) {
                // try by external shipment id stored previously
                $sref = $this->db->real_escape_string($payload['shipment_id']);
                $res2 = $this->db->query("SELECT * FROM shipments WHERE external_shipment_id = '{$sref}' LIMIT 1");
                if ($res2 && $res2->num_rows) {
                    $row2 = $res2->fetch_assoc();
                    $shipmentId = $row2['id'];
                    $res2->close();
                }
            }

            if ($shipmentId) {
                // update status/meta
                $status = $this->db->real_escape_string($payload['status'] ?? 'updated');
                $meta = $this->db->real_escape_string(json_encode($payload));
                $this->db->query("UPDATE shipments SET status = '{$status}', meta = '{$meta}', updated_at = NOW() WHERE id = {$shipmentId}");
            } else {
                // create simple shipment record
                $orderNumber = $payload['order_number'] ?? null;
                $tracking = $this->db->real_escape_string($payload['tracking_number'] ?? null);
                $extId = $this->db->real_escape_string($payload['shipment_id'] ?? null);
                $status = $this->db->real_escape_string($payload['status'] ?? 'created');
                $meta = $this->db->real_escape_string(json_encode($payload));
                $this->db->query("INSERT INTO shipments (external_shipment_id, tracking_number, carrier, order_number, status, meta, created_at) VALUES ('{$extId}', '{$tracking}', '{$carrier}', '{$orderNumber}', '{$status}', '{$meta}', NOW())");
                $shipmentId = $this->db->insert_id;
            }

            // optionally notify user about status change (placeholder)
            // Notification::send(...)

            return ['success' => true, 'shipment_id' => $shipmentId];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    //
    // Simple stats
    //
    public function getStatistics()
    {
        $stats = ['methods' => 0, 'zones' => 0, 'rates' => 0];

        $r = $this->db->query("SELECT COUNT(*) as cnt FROM shipping_methods");
        if ($r) { $row = $r->fetch_assoc(); $stats['methods'] = (int)$row['cnt']; $r->close(); }

        $r2 = $this->db->query("SELECT COUNT(*) as cnt FROM shipping_zones");
        if ($r2) { $row2 = $r2->fetch_assoc(); $stats['zones'] = (int)$row2['cnt']; $r2->close(); }

        $r3 = $this->db->query("SELECT COUNT(*) as cnt FROM shipping_rates");
        if ($r3) { $row3 = $r3->fetch_assoc(); $stats['rates'] = (int)$row3['cnt']; $r3->close(); }

        return $stats;
    }
}

?>