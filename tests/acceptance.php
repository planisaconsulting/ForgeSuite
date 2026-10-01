<?php

declare(strict_types=1);

/**
 * HTTP checks for Phase 1. The dev server must already be running.
 *
 *   php tests/acceptance.php
 *   php tests/acceptance.php http://127.0.0.1:8741
 *
 * The seeded admin is forced to change password. This script does that,
 * exercises the desk, then puts the seed password and the force-change
 * flag back so the documented login still works.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8741', '/');
$failures = 0;
$jar = tempnam(sys_get_temp_dir(), 'sfjar');

$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};

$http = static function (string $method, string $path, array $fields = [], bool $follow = false) use ($base, $jar): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $follow,
    ]);
    if ($fields !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException(curl_error($ch));
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    return [$status, $headers, $body];
};

$tokenFrom = static function (string $html): string {
    if (!preg_match('/name="_token" value="([^"]+)"/', $html, $match) &&
        !preg_match('/name="csrf-token" content="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('No CSRF token on the page.');
    }

    return html_entity_decode($match[1], ENT_QUOTES);
};

$redirect = static function (string $headers): string {
    if (!preg_match('/^Location:\s*(\S+)/mi', $headers, $match)) {
        return '';
    }

    return $match[1];
};

try {
    $pdo = Database::connection();
    $pdo->exec("DELETE FROM product_price_history WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'ACC-%')");
    $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'product' AND entity_id IN (SELECT id FROM products WHERE sku LIKE 'ACC-%')");
    $pdo->exec("DELETE FROM products WHERE sku LIKE 'ACC-%'");
    $pdo->exec("DELETE FROM crm_activities WHERE customer_id IN (SELECT id FROM customers WHERE email LIKE '%abc-engineering.example')");
    $pdo->exec("DELETE FROM customer_contacts WHERE customer_id IN (SELECT id FROM customers WHERE email LIKE '%abc-engineering.example')");
    $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'customer' AND entity_id IN (SELECT id FROM customers WHERE email LIKE '%abc-engineering.example')");
    $pdo->exec("DELETE FROM customers WHERE email LIKE '%abc-engineering.example'");
    $pdo->exec("DELETE FROM suppliers WHERE email = 'desk@acceptance-supplies.example'");
    $pdo->exec("DELETE FROM users WHERE email = 'sales@signforge.local'");

    [$status, $headers] = $http('GET', '/customers');
    if ($status === 302 && str_contains($redirect($headers), '/login')) {
        $ok('protected page redirects to login');
    } else {
        $fail('protected page status ' . $status . ' location ' . $redirect($headers));
    }

    [$status, , $body] = $http('GET', '/login');
    if ($status !== 200 || !str_contains($body, 'Sign-Forge Management System')) {
        $fail('login page did not render');
    } else {
        $ok('login page renders');
    }
    $token = $tokenFrom($body);
    [$status, , $body] = $http('POST', '/login', [
        '_token' => $token,
        'email' => 'admin@signforge.local',
        'password' => 'wrong-password',
    ]);
    if ($status === 200 && str_contains($body, 'do not match')) {
        $ok('invalid login is rejected');
    } else {
        $fail('invalid login status ' . $status);
    }

    [$status, , $body] = $http('GET', '/login');
    $token = $tokenFrom($body);
    [$status, $headers] = $http('POST', '/login', [
        '_token' => $token,
        'email' => 'admin@signforge.local',
        'password' => 'Forge#Admin2026',
    ]);
    if ($status === 302 && str_contains($redirect($headers), '/account/password')) {
        $ok('valid login forces a password change');
    } else {
        $fail('valid login status ' . $status . ' location ' . $redirect($headers));
    }

    [$status, , $body] = $http('GET', '/account/password');
    $token = $tokenFrom($body);
    [$status, $headers] = $http('POST', '/account/password', [
        '_token' => $token,
        'current_password' => 'Forge#Admin2026',
        'new_password' => 'Forge#Accept2026',
        'confirm_password' => 'Forge#Accept2026',
    ]);
    if ($status === 302) {
        $ok('password change accepted');
    } else {
        $fail('password change status ' . $status);
    }

    [$status, , $body] = $http('GET', '/');
    if ($status === 200 && str_contains($body, 'Workshop desk') && str_contains($body, 'Open quotes') && str_contains($body, 'not stored yet')) {
        $ok('dashboard shows real counts and does not invent quote totals');
    } else {
        $fail('dashboard did not render as expected, status ' . $status);
    }

    [$status, , $body] = $http('GET', '/quotes');
    if ($status === 200 && str_contains($body, 'Nothing is stored for them yet')) {
        $ok('coming soon quotes page does not error');
    } else {
        $fail('quotes coming soon status ' . $status);
    }

    [$status, , $body] = $http('GET', '/customers/new');
    $token = $tokenFrom($body);
    [$status, $headers] = $http('POST', '/customers', [
        '_token' => $token,
        'customer_type' => 'BUSINESS',
        'company_name' => 'ABC Engineering',
        'email' => 'office@abc-engineering.example',
        'phone' => '0115550199',
        'billing_address' => '1 Foundry Road',
        'physical_address' => '2 Site Road',
        'notes' => 'Acceptance customer',
        'active' => '1',
    ]);
    if ($status !== 302 || !preg_match('#/customers/(\d+)$#', $redirect($headers), $customerMatch)) {
        $fail('create customer status ' . $status . ' location ' . $redirect($headers));
        $customerId = '0';
    } else {
        $customerId = $customerMatch[1];
        $ok('customer created');
    }

    [$status, , $body] = $http('GET', '/customers/' . $customerId . '/edit');
    $token = $tokenFrom($body);
    [$status] = $http('POST', '/customers/' . $customerId, [
        '_token' => $token,
        'customer_type' => 'BUSINESS',
        'company_name' => 'ABC Engineering Pty',
        'email' => 'office@abc-engineering.example',
        'active' => '1',
    ]);
    if ($status === 302) {
        $ok('customer edited');
    } else {
        $fail('edit customer status ' . $status);
    }

    [$status, , $body] = $http('GET', '/customers?q=ABC+Engineering');
    if ($status === 200 && str_contains($body, 'ABC Engineering Pty')) {
        $ok('customer search finds the edited name');
    } else {
        $fail('customer search missed the record');
    }

    [$status, , $body] = $http('GET', '/customers/' . $customerId);
    $token = $tokenFrom($body);
    [$status] = $http('POST', '/customers/' . $customerId . '/contacts', [
        '_token' => $token,
        'name' => 'John Smith',
        'position' => 'Owner',
        'email' => 'john@abc-engineering.example',
        'primary_contact' => '1',
        'active' => '1',
    ]);
    if ($status !== 302) {
        $fail('add contact status ' . $status);
    }
    [$status, , $body] = $http('GET', '/customers/' . $customerId);
    $token = $tokenFrom($body);
    [$status] = $http('POST', '/customers/' . $customerId . '/activities', [
        '_token' => $token,
        'activity_type' => 'CALL',
        'subject' => 'Discussed shopfront',
        'description' => 'Asked for a site visit.',
        'activity_date' => date('Y-m-d'),
        'completed' => '0',
    ]);
    [$status, , $body] = $http('GET', '/customers/' . $customerId);
    if ($status === 200 && str_contains($body, 'John Smith') && str_contains($body, 'Discussed shopfront') && str_contains($body, 'Quotations for this customer')) {
        $ok('contact, activity, and future placeholders show on the customer');
    } else {
        $fail('customer detail is missing contact, activity, or placeholders');
    }

    [$status, , $body] = $http('GET', '/customers/' . $customerId);
    $token = $tokenFrom($body);
    [$status] = $http('POST', '/customers/' . $customerId . '/active', [
        '_token' => $token,
        'active' => '0',
    ]);
    [$status, , $body] = $http('GET', '/customers?status=inactive&q=ABC');
    if ($status === 200 && str_contains($body, 'ABC Engineering Pty')) {
        $ok('customer deactivated and still searchable');
    } else {
        $fail('deactivated customer was not listed');
    }

    [$status] = $http('GET', '/customers/999999');
    if ($status === 404) {
        $ok('unknown customer id returns 404');
    } else {
        $fail('unknown customer status ' . $status);
    }
    [$status] = $http('GET', '/customers/not-a-number');
    if ($status === 404) {
        $ok('invalid customer id returns 404');
    } else {
        $fail('invalid customer id status ' . $status);
    }

    $category = Database::connection()->query("SELECT id FROM product_categories WHERE name = 'Paint' LIMIT 1")->fetch();
    $supplier = Database::connection()->query("SELECT id FROM suppliers WHERE name = 'Demo Media Supplies' LIMIT 1")->fetch();
    $categoryId = (string) $category['id'];
    $supplierId = (string) $supplier['id'];

    $makeProduct = static function (array $fields) use ($http, $tokenFrom, $redirect, $fail, $ok): string {
        [$status, , $body] = $http('GET', '/products/new');
        $token = $tokenFrom($body);
        $fields['_token'] = $token;
        [$status, $headers, $body] = $http('POST', '/products', $fields);
        if ($status !== 302 || !preg_match('#/products/(\d+)$#', $redirect($headers), $match)) {
            $fail('create product ' . ($fields['sku'] ?? '') . ' status ' . $status . ' ' . substr(strip_tags($body), 0, 180));

            return '0';
        }
        $ok('created ' . $fields['pricing_method'] . ' product ' . $fields['sku']);

        return $match[1];
    };

    $areaId = $makeProduct([
        'name' => 'Acceptance vinyl',
        'sku' => 'ACC-AREA',
        'category_id' => $categoryId,
        'supplier_id' => $supplierId,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'AREA',
        'cost_price' => '50',
        'roll_width_mm' => '1300',
        'standard_waste_percent' => '10',
        'waste_threshold_percent' => '50',
        'default_waste_policy' => 'ACTUAL',
        'allow_rotation' => '1',
        'active' => '1',
    ]);
    $linearId = $makeProduct([
        'name' => 'Acceptance tube',
        'sku' => 'ACC-LINEAR',
        'category_id' => $categoryId,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'LINEAR_METRE',
        'cost_price' => '48',
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
    ]);
    $unitId = $makeProduct([
        'name' => 'Acceptance module',
        'sku' => 'ACC-UNIT',
        'category_id' => $categoryId,
        'product_type' => 'COMPONENT',
        'pricing_method' => 'UNIT',
        'cost_price' => '18.50',
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
    ]);
    $sheetId = $makeProduct([
        'name' => 'Acceptance sheet',
        'sku' => 'ACC-SHEET',
        'category_id' => $categoryId,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'SHEET',
        'cost_price' => '100',
        'sheet_width_mm' => '1000',
        'sheet_height_mm' => '1000',
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
    ]);

    [$status, , $body] = $http('GET', '/products/' . $areaId . '/edit');
    $token = $tokenFrom($body);
    [$status] = $http('POST', '/products/' . $areaId, [
        '_token' => $token,
        'name' => 'Acceptance vinyl',
        'sku' => 'ACC-AREA',
        'category_id' => $categoryId,
        'supplier_id' => $supplierId,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'AREA',
        'cost_price' => '55',
        'roll_width_mm' => '1300',
        'standard_waste_percent' => '10',
        'waste_threshold_percent' => '50',
        'default_waste_policy' => 'ACTUAL',
        'allow_rotation' => '1',
        'active' => '1',
    ]);
    [$status, , $body] = $http('GET', '/products/' . $areaId);
    if ($status === 200 && str_contains($body, 'Price history') && str_contains($body, 'R 50.00') && str_contains($body, 'R 55.00')) {
        $ok('cost change wrote price history');
    } else {
        $fail('price history was not shown');
    }

    $postJson = static function (array $fields) use ($base, $jar, $tokenFrom, $http): array {
        [$status, , $page] = $http('GET', '/calculator');
        $token = $tokenFrom($page);
        $ch = curl_init($base . '/calculator/price');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Requested-With: fetch',
                'X-CSRF-Token: ' . $token,
            ],
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true);

        return [$status, is_array($data) ? $data : []];
    };

    [$status, $data] = $postJson([
        'product_id' => $areaId,
        'width_mm' => '800',
        'height_mm' => '1000',
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
        'cost' => '0.01',
        'markup' => '999',
    ]);
    $checkQty = static function (string $label, array $data, string $key, string $expected) use ($fail, $ok): void {
        $actual = (string) ($data[$key] ?? '');
        if ($actual === $expected) {
            $ok($label);

            return;
        }
        $fail($label . ' expected ' . $expected . ' got ' . $actual);
    };
    if ($status !== 200) {
        $fail('calculator actual status ' . $status . ' ' . json_encode($data['errors'] ?? []));
    } else {
        $checkQty('800 x 1000 actual area', $data, 'actual_quantity', '0.8000');
        $checkQty('ACTUAL billable', $data, 'billable_quantity', '0.8000');
        $checkQty('10 percent manufacturing waste', $data, 'costed_quantity', '0.8800');
        if (($data['total_cost'] ?? '') === '48.40' && ($data['raw_cost'] ?? '') === '44.00') {
            $ok('server recalculated 0.88 m2 x R55 and ignored the browser cost');
        } else {
            $fail('unexpected cost raw ' . ($data['raw_cost'] ?? '') . ' total ' . ($data['total_cost'] ?? ''));
        }
        if (($data['roll']['width_utilisation_percent'] ?? '') === '61.5385'
            && ($data['roll']['consumed_area_m2'] ?? '') === '1.3000'
            && ($data['roll']['potential_waste_m2'] ?? '') === '0.5000') {
            $ok('roll utilisation, consumed area, and waste');
        } else {
            $fail('roll picture ' . json_encode($data['roll'] ?? []));
        }
    }

    [$status, $data] = $postJson([
        'product_id' => $areaId,
        'width_mm' => '800',
        'height_mm' => '1000',
        'quantity' => '1',
        'waste_mode' => 'CONSUMED_WIDTH',
    ]);
    $checkQty('CONSUMED_WIDTH billable', $data, 'billable_quantity', '1.3000');

    [$status, $data] = $postJson([
        'product_id' => $areaId,
        'width_mm' => '800',
        'height_mm' => '1000',
        'quantity' => '1',
        'waste_mode' => 'MANUAL',
        'manual_area' => '0.95',
    ]);
    $checkQty('MANUAL area', $data, 'billable_quantity', '0.9500');
    if (empty($data['manual'])) {
        $fail('manual mode was not flagged');
    } else {
        $ok('manual mode is flagged');
    }

    [$status, $data] = $postJson([
        'product_id' => $areaId,
        'width_mm' => '1000',
        'height_mm' => '2000',
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
    ]);
    $checkQty('2 m2 plus 10 percent', $data, 'costed_quantity', '2.2000');

    [$status, $data] = $postJson([
        'product_id' => $linearId,
        'length_mm' => '2500',
        'quantity' => '4',
    ]);
    $checkQty('2500 mm x 4', $data, 'billable_quantity', '10.0000');
    if (($data['total_cost'] ?? '') === '480.00') {
        $ok('linear cost 10 x 48');
    } else {
        $fail('linear cost ' . ($data['total_cost'] ?? ''));
    }

    [$status, $data] = $postJson([
        'product_id' => $unitId,
        'quantity' => '10',
        'cost' => '1',
    ]);
    if (($data['total_cost'] ?? '') === '185.00') {
        $ok('10 units x 18.50');
    } else {
        $fail('unit cost ' . ($data['total_cost'] ?? ''));
    }

    [$status, $data] = $postJson([
        'product_id' => $sheetId,
        'width_mm' => '500',
        'height_mm' => '500',
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
    ]);
    if (($data['total_cost'] ?? '') === '25.00') {
        $ok('sheet actual-area cost');
    } else {
        $fail('sheet actual cost ' . ($data['total_cost'] ?? ''));
    }
    [$status, $data] = $postJson([
        'product_id' => $sheetId,
        'width_mm' => '500',
        'height_mm' => '500',
        'quantity' => '1',
        'waste_mode' => 'FULL_SHEET',
    ]);
    if (($data['total_cost'] ?? '') === '100.00') {
        $ok('full sheet cost');
    } else {
        $fail('full sheet cost ' . ($data['total_cost'] ?? ''));
    }

    $levels = new PricingLevelRepository();
    $q1 = null;
    foreach ($levels->all() as $level) {
        if ($level['code'] === 'Q1') {
            $q1 = $level;
        }
    }
    if ($q1 === null) {
        $fail('Q1 missing');
    } else {
        [$status, , $body] = $http('GET', '/pricing-levels/' . $q1['id'] . '/edit');
        $token = $tokenFrom($body);
        $http('POST', '/pricing-levels/' . $q1['id'], [
            '_token' => $token,
            'name' => 'Q1',
            'markup_percent' => '80',
            'sort_order' => '10',
            'active' => '1',
        ]);
        [$status, $data] = $postJson([
            'product_id' => $unitId,
            'quantity' => '1',
        ]);
        $q1Price = null;
        foreach ($data['levels'] ?? [] as $level) {
            if ($level['code'] === 'Q1') {
                $q1Price = $level['selling_price'];
            }
        }
        if ($q1Price === '33.30') {
            $ok('calculator uses the edited Q1 markup');
        } else {
            $fail('Q1 price after markup change was ' . (string) $q1Price);
        }
        [$status, , $body] = $http('GET', '/pricing-levels/' . $q1['id'] . '/edit');
        $token = $tokenFrom($body);
        $http('POST', '/pricing-levels/' . $q1['id'], [
            '_token' => $token,
            'name' => 'Q1',
            'markup_percent' => '65',
            'sort_order' => '10',
            'active' => '1',
        ]);
    }

    [$status, , $body] = $http('GET', '/suppliers/new');
    $token = $tokenFrom($body);
    [$status, $headers] = $http('POST', '/suppliers', [
        '_token' => $token,
        'name' => 'Acceptance Supplies',
        'email' => 'desk@acceptance-supplies.example',
        'phone' => '0100000000',
        'active' => '1',
    ]);
    if ($status === 302 && preg_match('#/suppliers/(\d+)$#', $redirect($headers), $supplierMatch)) {
        $ok('supplier created');
        $newSupplier = $supplierMatch[1];
        [$status, , $body] = $http('GET', '/suppliers/' . $newSupplier . '/edit');
        $token = $tokenFrom($body);
        $http('POST', '/suppliers/' . $newSupplier, [
            '_token' => $token,
            'name' => 'Acceptance Supplies Updated',
            'email' => 'desk@acceptance-supplies.example',
            'active' => '1',
        ]);
        [$status, , $body] = $http('GET', '/suppliers?q=Acceptance+Supplies+Updated');
        if (str_contains($body, 'Acceptance Supplies Updated')) {
            $ok('supplier edited and searchable');
        } else {
            $fail('supplier search missed the edit');
        }
    } else {
        $fail('create supplier status ' . $status);
    }

    [$status, , $body] = $http('GET', '/users/new');
    $token = $tokenFrom($body);
    $salesRole = Database::connection()->query("SELECT id FROM roles WHERE code = 'SALES' LIMIT 1")->fetch();
    $http('POST', '/users', [
        '_token' => $token,
        'name' => 'Sales Tester',
        'email' => 'sales@signforge.local',
        'role_id' => (string) $salesRole['id'],
        'password' => 'Forge#Sales2026',
        'active' => '1',
    ]);

    [$status, , $body] = $http('GET', '/products/' . $areaId);
    $token = $tokenFrom($body);
    $http('POST', '/products/' . $areaId . '/active', ['_token' => $token, 'active' => '0']);
    $gone = (new ProductRepository())->find((int) $areaId);
    if ($gone !== null && (int) $gone['active'] === 0) {
        $ok('product deactivated rather than deleted');
    } else {
        $fail('product deactivation did not stick');
    }

    [$status, , $body] = $http('GET', '/');
    $token = $tokenFrom($body);
    [$status, $headers] = $http('POST', '/logout', ['_token' => $token]);
    if ($status === 302 && str_contains($redirect($headers), '/login')) {
        $ok('logout');
    } else {
        $fail('logout status ' . $status);
    }
    [$status, $headers] = $http('GET', '/products');
    if ($status === 302 && str_contains($redirect($headers), '/login')) {
        $ok('logged-out catalogue is protected');
    } else {
        $fail('post-logout status ' . $status);
    }

    $salesJar = tempnam(sys_get_temp_dir(), 'sfsales');
    $sales = static function (string $method, string $path, array $fields = []) use ($base, $salesJar): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEJAR => $salesJar,
            CURLOPT_COOKIEFILE => $salesJar,
        ]);
        if ($fields !== []) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [$status, substr((string) $raw, 0, $headerSize), substr((string) $raw, $headerSize)];
    };
    [$status, , $body] = $sales('GET', '/login');
    $token = $tokenFrom($body);
    $sales('POST', '/login', [
        '_token' => $token,
        'email' => 'sales@signforge.local',
        'password' => 'Forge#Sales2026',
    ]);
    [$status, $headers, $body] = $sales('GET', '/account/password');
    if ($status === 200) {
        $token = $tokenFrom($body);
        $sales('POST', '/account/password', [
            '_token' => $token,
            'current_password' => 'Forge#Sales2026',
            'new_password' => 'Forge#SalesChanged',
            'confirm_password' => 'Forge#SalesChanged',
        ]);
    }
    [$status] = $sales('GET', '/settings');
    if ($status === 403) {
        $ok('sales role cannot open settings');
    } else {
        $fail('sales settings status ' . $status);
    }
    [$status] = $sales('GET', '/customers');
    if ($status === 200) {
        $ok('sales role can open customers');
    } else {
        $fail('sales customers status ' . $status);
    }
    @unlink($salesJar);
} finally {
    $hash = '$2y$10$PzpWv1xje7w4bt5JvKD9re4FB3yUIPVkvILy5xtAYctGXMf3zUFfy';
    $stmt = Database::connection()->prepare(
        'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE email = ?'
    );
    $stmt->execute([$hash, 'admin@signforge.local']);
    @unlink($jar);
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll acceptance checks passed.\n");
