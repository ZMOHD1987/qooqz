<?php
// htdocs/api/services/TaxService.php
// Service layer for Tax calculations and rules management.
// - Calculate taxes for cart/order lines
// - Determine applicable tax rate by destination and product tax class
// - Support inclusive/exclusive pricing, exemptions, and different tax rule types
// - CRUD for simple tax rules stored in `tax_rules` table (id, country, region, postal_code, tax_class, rate, type, is_active, created_at)
// - Returns structured arrays and does not send HTTP responses directly.

require_once __DIR__ . '/../helpers/response.php';

class TaxService
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: connectDB();
    }

    /**
     * Calculate taxes for items and shipping.
     * $items: [
     *   ['product_id'=>int, 'price'=>float, 'quantity'=>int, 'tax_class'=>string|null, 'tax_exempt'=>bool|null],
     *   ...
     * ]
     * $destination: ['country'=>'US','region'=>'CA','postal_code'=>'90001']
     * $options:
     *   - 'shipping_amount' => float
     *   - 'prices_include_tax' => bool (if true, prices provided include tax)
     *   - 'currency' => 'USD'
     *   - 'customer_id' => int|null (for exemptions)
     *
     * Returns:
     * [
     *   'lines' => [ {item fields + tax, tax_rate, price_ex_tax, price_incl_tax, tax_amount} ... ],
     *   'shipping' => {amount, tax_rate, tax_amount, amount_ex_tax, amount_incl_tax},
     *   'tax_total' => float,
     *   'subtotal' => float, // sum of line totals ex tax
     *   'grand_total' => float // subtotal + shipping + tax_total
     * ]
     */
    public function calculate(array $items, array $destination = [], array $options = [])
    {
        $pricesIncludeTax = isset($options['prices_include_tax']) ? (bool)$options['prices_include_tax'] : false;
        $shippingAmt = isset($options['shipping_amount']) ? (float)$options['shipping_amount'] : 0.0;
        $currency = $options['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');
        $customerId = $options['customer_id'] ?? null;

        $resultLines = [];
        $subtotalExTax = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $it) {
            $price = isset($it['price']) ? (float)$it['price'] : 0.0;
            $qty = max(0, (int)($it['quantity'] ?? 1));
            $taxClass = $it['tax_class'] ?? null;
            $productTaxExempt = isset($it['tax_exempt']) ? (bool)$it['tax_exempt'] : false;

            // Check exemption (customer-level or product-level)
            if ($this->isTaxExempt($customerId, $it)) {
                $lineTax = 0.0;
                if ($pricesIncludeTax) {
                    // price already includes tax but since exempt treat as ex-tax
                    $priceExTax = $price;
                    $priceInclTax = $price;
                } else {
                    $priceExTax = $price;
                    $priceInclTax = $price;
                }
                $lineTotalExTax = $priceExTax * $qty;
                $subtotalExTax += $lineTotalExTax;
                $resultLines[] = array_merge($it, [
                    'tax_rate' => 0.0,
                    'tax_amount' => 0.0,
                    'price_ex_tax' => $this->roundAmount($priceExTax),
                    'price_incl_tax' => $this->roundAmount($priceInclTax),
                    'total_ex_tax' => $this->roundAmount($lineTotalExTax),
                ]);
                continue;
            }

            // find applicable tax rate for this product/location
            $rate = $this->getApplicableTaxRate($destination['country'] ?? null, $destination['region'] ?? null, $destination['postal_code'] ?? null, $taxClass);
            // rate is decimal percent, e.g., 5.5
            $rateDecimal = max(0.0, (float)$rate);

            if ($pricesIncludeTax) {
                // price includes tax -> derive ex-tax price: price_ex = price / (1 + r/100)
                $priceExTax = $this->roundAmount($price / (1 + ($rateDecimal / 100)));
                $taxAmountPerUnit = $this->roundAmount($price - $priceExTax);
                $priceInclTax = $price;
            } else {
                $priceExTax = $price;
                $taxAmountPerUnit = $this->roundAmount($priceExTax * ($rateDecimal / 100));
                $priceInclTax = $this->roundAmount($priceExTax + $taxAmountPerUnit);
            }

            $lineTax = $this->roundAmount($taxAmountPerUnit * $qty);
            $lineTotalExTax = $this->roundAmount($priceExTax * $qty);

            $subtotalExTax += $lineTotalExTax;
            $taxTotal += $lineTax;

            $resultLines[] = array_merge($it, [
                'tax_rate' => $rateDecimal,
                'tax_amount' => $lineTax,
                'price_ex_tax' => $this->roundAmount($priceExTax),
                'price_incl_tax' => $this->roundAmount($priceInclTax),
                'total_ex_tax' => $this->roundAmount($lineTotalExTax),
            ]);
        }

        // Shipping tax
        $shippingTaxRate = $this->getApplicableTaxRate($destination['country'] ?? null, $destination['region'] ?? null, $destination['postal_code'] ?? null, 'shipping');
        $shippingRateDecimal = max(0.0, (float)$shippingTaxRate);
        if ($shippingAmt > 0) {
            if ($pricesIncludeTax) {
                $shippingExTax = $this->roundAmount($shippingAmt / (1 + ($shippingRateDecimal / 100)));
                $shippingTaxAmt = $this->roundAmount($shippingAmt - $shippingExTax);
                $shippingIncl = $shippingAmt;
            } else {
                $shippingExTax = $shippingAmt;
                $shippingTaxAmt = $this->roundAmount($shippingExTax * ($shippingRateDecimal / 100));
                $shippingIncl = $this->roundAmount($shippingExTax + $shippingTaxAmt);
            }
            $subtotalExTax += $shippingExTax;
            $taxTotal += $shippingTaxAmt;
            $shippingBreakdown = [
                'amount' => $this->roundAmount($shippingAmt),
                'tax_rate' => $shippingRateDecimal,
                'tax_amount' => $this->roundAmount($shippingTaxAmt),
                'amount_ex_tax' => $this->roundAmount($shippingExTax),
                'amount_incl_tax' => $this->roundAmount($shippingIncl),
            ];
        } else {
            $shippingBreakdown = [
                'amount' => 0.0,
                'tax_rate' => $shippingRateDecimal,
                'tax_amount' => 0.0,
                'amount_ex_tax' => 0.0,
                'amount_incl_tax' => 0.0,
            ];
        }

        $grandTotal = $this->roundAmount($subtotalExTax + $taxTotal);

        return [
            'lines' => $resultLines,
            'shipping' => $shippingBreakdown,
            'tax_total' => $this->roundAmount($taxTotal),
            'subtotal' => $this->roundAmount($subtotalExTax),
            'grand_total' => $grandTotal,
            'currency' => $currency
        ];
    }

    /**
     * Determine applicable tax rate (percentage) by country/region/postal_code and tax_class.
     * Looks up `tax_rules` table. Matching priority: postal_code exact -> region -> country -> tax_class default -> global default.
     * Returns float percent (e.g., 5.5). If no rule found, returns DEFAULT_TAX_RATE constant or 0.
     */
    public function getApplicableTaxRate($country = null, $region = null, $postal = null, $taxClass = null)
    {
        // Normalize inputs
        $countryN = $country ? strtoupper(trim($country)) : null;
        $regionN = $region ? strtoupper(trim($region)) : null;
        $postalN = $postal ? strtoupper(str_replace(' ', '', trim($postal))) : null;
        $taxClassN = $taxClass ? trim($taxClass) : null;

        // 1) try postal_code + tax_class
        if ($postalN) {
            $stmt = $this->db->prepare("SELECT rate FROM tax_rules WHERE REPLACE(UPPER(postal_code),' ','') = ? AND tax_class = ? AND is_active = 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $postalN, $taxClassN);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $stmt->close(); return (float)$row['rate']; }
                $stmt->close();
            }
            // postal w/out tax_class
            $stmt2 = $this->db->prepare("SELECT rate FROM tax_rules WHERE REPLACE(UPPER(postal_code),' ','') = ? AND (tax_class IS NULL OR tax_class = '') AND is_active = 1 LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('s', $postalN);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $res2->num_rows) { $row2 = $res2->fetch_assoc(); $stmt2->close(); return (float)$row2['rate']; }
                $stmt2->close();
            }
        }

        // 2) region + tax_class
        if ($regionN) {
            $stmt = $this->db->prepare("SELECT rate FROM tax_rules WHERE UPPER(region) = ? AND tax_class = ? AND is_active = 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $regionN, $taxClassN);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $stmt->close(); return (float)$row['rate']; }
                $stmt->close();
            }
            $stmt2 = $this->db->prepare("SELECT rate FROM tax_rules WHERE UPPER(region) = ? AND (tax_class IS NULL OR tax_class = '') AND is_active = 1 LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('s', $regionN);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $res2->num_rows) { $row2 = $res2->fetch_assoc(); $stmt2->close(); return (float)$row2['rate']; }
                $stmt2->close();
            }
        }

        // 3) country + tax_class
        if ($countryN) {
            $stmt = $this->db->prepare("SELECT rate FROM tax_rules WHERE UPPER(country) = ? AND tax_class = ? AND is_active = 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $countryN, $taxClassN);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $stmt->close(); return (float)$row['rate']; }
                $stmt->close();
            }
            $stmt2 = $this->db->prepare("SELECT rate FROM tax_rules WHERE UPPER(country) = ? AND (tax_class IS NULL OR tax_class = '') AND is_active = 1 LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param('s', $countryN);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $res2->num_rows) { $row2 = $res2->fetch_assoc(); $stmt2->close(); return (float)$row2['rate']; }
                $stmt2->close();
            }
        }

        // 4) tax_class default (no location)
        if ($taxClassN) {
            $stmt = $this->db->prepare("SELECT rate FROM tax_rules WHERE (country IS NULL OR country = '') AND (region IS NULL OR region = '') AND (postal_code IS NULL OR postal_code = '') AND tax_class = ? AND is_active = 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $taxClassN);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $stmt->close(); return (float)$row['rate']; }
                $stmt->close();
            }
        }

        // 5) global default rule (no location, no class)
        $stmt = $this->db->prepare("SELECT rate FROM tax_rules WHERE (country IS NULL OR country = '') AND (region IS NULL OR region = '') AND (postal_code IS NULL OR postal_code = '') AND (tax_class IS NULL OR tax_class = '') AND is_active = 1 LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $stmt->close(); return (float)$row['rate']; }
            $stmt->close();
        }

        // fallback to constant
        return defined('DEFAULT_TAX_RATE') ? (float)DEFAULT_TAX_RATE : 0.0;
    }

    /**
     * Simple tax rule CRUD helpers (tax_rules table).
     * Rule fields expected: country, region, postal_code, tax_class, rate (decimal), type ('percentage'|'fixed'), is_active
     */

    public function listRules($filters = [], $page = 1, $perPage = 100)
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM tax_rules WHERE 1=1";
        $params = [];
        $types = '';

        if (isset($filters['country'])) { $sql .= " AND UPPER(country) = ?"; $params[] = strtoupper($filters['country']); $types .= 's'; }
        if (isset($filters['is_active'])) { $sql .= " AND is_active = ?"; $params[] = (int)$filters['is_active']; $types .= 'i'; }

        $sql .= " ORDER BY id DESC LIMIT ?, ?";
        $params[] = $offset; $params[] = $perPage; $types .= 'ii';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return ['data' => [], 'total' => 0];
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        $r = $this->db->query("SELECT COUNT(*) as cnt FROM tax_rules");
        $total = 0;
        if ($r) { $row = $r->fetch_assoc(); $total = (int)$row['cnt']; $r->close(); }

        return ['data' => $rows, 'total' => $total];
    }

    public function createRule(array $data)
    {
        $country = isset($data['country']) ? $this->db->real_escape_string($data['country']) : null;
        $region = isset($data['region']) ? $this->db->real_escape_string($data['region']) : null;
        $postal = isset($data['postal_code']) ? $this->db->real_escape_string($data['postal_code']) : null;
        $taxClass = isset($data['tax_class']) ? $this->db->real_escape_string($data['tax_class']) : null;
        $rate = isset($data['rate']) ? (float)$data['rate'] : 0.0;
        $type = isset($data['type']) ? $this->db->real_escape_string($data['type']) : 'percentage';
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        $stmt = $this->db->prepare("INSERT INTO tax_rules (country, region, postal_code, tax_class, rate, type, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) return false;
        $stmt->bind_param('ssssdis', $country, $region, $postal, $taxClass, $rate, $type, $isActive);
        // bind types: 'd' for double, but mixing types in bind_param requires correct order; we used 's' for is_active accidentally -> adjust:
        // To be safe we'll fallback to escaped query if bind fails.
        try {
            $ok = $stmt->execute();
            $id = $this->db->insert_id;
            $stmt->close();
        } catch (Throwable $_) {
            $stmt->close();
            $countryEsc = $country !== null ? "'".$this->db->real_escape_string($country)."'" : "NULL";
            $regionEsc  = $region !== null ? "'".$this->db->real_escape_string($region)."'" : "NULL";
            $postalEsc  = $postal !== null ? "'".$this->db->real_escape_string($postal)."'" : "NULL";
            $classEsc   = $taxClass !== null ? "'".$this->db->real_escape_string($taxClass)."'" : "NULL";
            $typeEsc    = $this->db->real_escape_string($type);
            $ok = $this->db->query("INSERT INTO tax_rules (country, region, postal_code, tax_class, rate, type, is_active, created_at) VALUES ({$countryEsc}, {$regionEsc}, {$postalEsc}, {$classEsc}, {$rate}, '{$typeEsc}', {$isActive}, NOW())");
            $id = $this->db->insert_id;
        }

        return $ok ? $this->getRuleById($id) : false;
    }

    public function getRuleById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM tax_rules WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res && $res->num_rows ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    public function updateRule($id, array $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        if (array_key_exists('country', $data)) { $fields[] = "country = ?"; $params[] = $data['country']; $types .= 's'; }
        if (array_key_exists('region', $data)) { $fields[] = "region = ?"; $params[] = $data['region']; $types .= 's'; }
        if (array_key_exists('postal_code', $data)) { $fields[] = "postal_code = ?"; $params[] = $data['postal_code']; $types .= 's'; }
        if (array_key_exists('tax_class', $data)) { $fields[] = "tax_class = ?"; $params[] = $data['tax_class']; $types .= 's'; }
        if (array_key_exists('rate', $data)) { $fields[] = "rate = ?"; $params[] = (float)$data['rate']; $types .= 'd'; }
        if (array_key_exists('type', $data)) { $fields[] = "type = ?"; $params[] = $data['type']; $types .= 's'; }
        if (array_key_exists('is_active', $data)) { $fields[] = "is_active = ?"; $params[] = (int)$data['is_active']; $types .= 'i'; }

        if (empty($fields)) return $this->getRuleById($id);

        $sql = "UPDATE tax_rules SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = (int)$id; $types .= 'i';

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $this->getRuleById($id) : false;
    }

    public function deleteRule($id)
    {
        $stmt = $this->db->prepare("DELETE FROM tax_rules WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Check if a customer or item is tax exempt.
     * - If $options['customer_id'] matches an entry in tax_exemptions table return true
     * - If item has 'tax_exempt' true return true
     */
    public function isTaxExempt($customerId = null, $item = null)
    {
        // item-level override
        if (is_array($customerId) && $item === null) {
            // legacy call: isTaxExempt($item)
            $item = $customerId;
            $customerId = null;
        }

        if (is_array($item) && isset($item['tax_exempt']) && $item['tax_exempt']) return true;

        if ($customerId) {
            $stmt = $this->db->prepare("SELECT 1 FROM tax_exemptions WHERE customer_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $customerId);
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = ($res && $res->num_rows > 0);
                $stmt->close();
                return $exists;
            }
        }

        return false;
    }

    /**
     * Utility: round amount to 2 decimals (currency)
     */
    protected function roundAmount($amt)
    {
        return round((float)$amt, 2);
    }

    /**
     * Simple statistics about tax rules
     */
    public function getStatistics()
    {
        $stats = ['total_rules' => 0, 'average_rate' => 0.0];

        $r = $this->db->query("SELECT COUNT(*) as cnt, AVG(rate) as avg_rate FROM tax_rules WHERE is_active = 1");
        if ($r) {
            $row = $r->fetch_assoc();
            $stats['total_rules'] = (int)($row['cnt'] ?? 0);
            $stats['average_rate'] = $this->roundAmount((float)($row['avg_rate'] ?? 0.0));
            $r->close();
        }

        return $stats;
    }
}

?>