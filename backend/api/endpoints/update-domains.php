<?php
/**
 * Update Domains Endpoint
 * Add or update restricted domains
 */

function handle_update_domains($pdo, $method) {
    if ($method !== 'POST' && $method !== 'PUT') {
        send_error('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['domains']) || !isset($data['user_id'])) {
        send_error('Missing required fields: domains (array), user_id', 400);
    }

    if (!verify_admin_key()) {
        send_unauthorized();
    }

    $user_id = $data['user_id'];
    $domains_list = $data['domains'];

    if (!is_array($domains_list)) {
        send_error('domains must be an array', 400);
    }

    try {
        $pdo->beginTransaction();

        $inserted = 0;
        $updated = 0;

        foreach ($domains_list as $domain_data) {
            if (!isset($domain_data['domain'])) {
                continue;
            }

            $domain_id = bin2hex(random_bytes(18));
            $domain = $domain_data['domain'];
            $status = $domain_data['status'] ?? 'restricted';
            $category = $domain_data['category'] ?? null;
            $reason = $domain_data['reason'] ?? null;

            // Try insert, if duplicate update
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO domains (id, user_id, domain, status, category, reason)
                    VALUES (:id, :user_id, :domain, :status, :category, :reason)
                ");

                $stmt->execute([
                    ':id' => $domain_id,
                    ':user_id' => $user_id,
                    ':domain' => $domain,
                    ':status' => $status,
                    ':category' => $category,
                    ':reason' => $reason
                ]);

                $inserted++;

            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $stmt = $pdo->prepare("
                        UPDATE domains
                        SET status = :status, category = :category, reason = :reason, updated_at = NOW()
                        WHERE domain = :domain AND user_id = :user_id
                    ");

                    $stmt->execute([
                        ':status' => $status,
                        ':category' => $category,
                        ':reason' => $reason,
                        ':domain' => $domain,
                        ':user_id' => $user_id
                    ]);

                    $updated++;
                } else {
                    throw $e;
                }
            }
        }

        $pdo->commit();

        send_success([
            'inserted' => $inserted,
            'updated' => $updated,
            'message' => 'Domains updated successfully'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>
