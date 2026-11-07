<?php
declare(strict_types=1);

/**
 * K6c — kurze Verbindungsprüfung (nur environment=test).
 */
final class CashTseConnectionCheck
{
    /**
     * @return array{ok: bool, message: string, state: string}
     */
    public static function runForRegister(int $registerId): array
    {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        $row = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($row === null) {
            throw new RuntimeException('Zuerst TSE-Zuordnung speichern.');
        }

        $env = strtolower(trim((string) ($row['environment'] ?? '')));
        if ($env !== 'test') {
            throw new RuntimeException('Verbindungsprüfung nur in der Test-Umgebung.');
        }

        $assignmentId = (int) ($row['id'] ?? 0);
        if ($assignmentId <= 0) {
            throw new RuntimeException('TSE-Zuordnung ungültig.');
        }

        $checkedAt = date('Y-m-d H:i:s');

        try {
            $provider = TseProviderFactory::fromAssignment($row);
            $status = $provider->getStatus();
            $ok = !empty($status['ok']);
            $state = trim((string) ($status['state'] ?? ''));
            $msg = trim((string) ($status['message'] ?? ''));
            $error = $ok ? '' : ($msg !== '' ? $msg : 'TSE meldet nicht bereit.');

            CashTseAssignmentRepository::update($assignmentId, [
                'last_state' => $state !== '' ? $state : ($ok ? 'OK' : 'ERROR'),
                'last_checked_at' => $checkedAt,
                'last_error' => mb_substr($error, 0, 500),
                'tss_serial_number' => trim((string) ($status['tss_serial_number'] ?? '')),
                'client_serial_number' => trim((string) ($status['client_serial_number'] ?? '')),
                'certificate_valid_until' => $status['certificate_valid_until'] ?? null,
            ]);

            if (!$ok) {
                return [
                    'ok' => false,
                    'message' => $error,
                    'state' => $state !== '' ? $state : 'ERROR',
                ];
            }

            $parts = ['Verbindung OK'];
            if ($state !== '') {
                $parts[] = 'Status: ' . $state;
            }
            if ($msg !== '') {
                $parts[] = $msg;
            }

            return [
                'ok' => true,
                'message' => implode(' — ', $parts),
                'state' => $state,
            ];
        } catch (Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 500);
            CashTseAssignmentRepository::update($assignmentId, [
                'last_state' => 'ERROR',
                'last_checked_at' => $checkedAt,
                'last_error' => $err,
            ]);

            return [
                'ok' => false,
                'message' => $err,
                'state' => 'ERROR',
            ];
        }
    }
}
