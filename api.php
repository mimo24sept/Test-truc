<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$pdo = db();
$user = getUser($pdo, (int) $_SESSION['user_id']);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Session invalide']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

$redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
$getColor = static function (int $number) use ($redNumbers): string {
    if ($number === 0) {
        return 'green';
    }
    return in_array($number, $redNumbers, true) ? 'red' : 'black';
};

try {
    switch ($action) {
        case 'spin':
            $bets = $input['bets'] ?? [];
            if (!is_array($bets) || empty($bets)) {
                throw new RuntimeException('Aucune mise reçue.');
            }

            $cleanBets = [];
            $totalBet = 0;
            foreach ($bets as $bet) {
                $type = $bet['type'] ?? '';
                $value = $bet['value'] ?? null;
                $amount = isset($bet['amount']) ? (int) $bet['amount'] : 0;
                if ($amount <= 0) {
                    continue;
                }
                if ($type === 'number') {
                    $value = (int) $value;
                    if ($value < 0 || $value > 36) {
                        continue;
                    }
                } elseif ($type === 'color') {
                    if (!in_array($value, ['red', 'black', 'green'], true)) {
                        continue;
                    }
                } else {
                    continue;
                }
                $cleanBets[] = ['type' => $type, 'value' => $value, 'amount' => $amount];
                $totalBet += $amount;
            }

            if ($totalBet <= 0) {
                throw new RuntimeException('Montant de mise invalide.');
            }

            if ($totalBet > $user['credits']) {
                throw new RuntimeException('Crédits insuffisants.');
            }

            $pdo->beginTransaction();

            $number = random_int(0, 36);
            $color = $getColor($number);
            $payout = 0;

            foreach ($cleanBets as $bet) {
                if ($bet['type'] === 'number' && (int)$bet['value'] === $number) {
                    $payout += $bet['amount'] * 36;
                }
                if ($bet['type'] === 'color' && $bet['value'] === $color) {
                    $payout += $bet['amount'] * ($color === 'green' ? 14 : 2);
                }
            }

            $newCredits = $user['credits'] - $totalBet + $payout;

            $update = $pdo->prepare('UPDATE users SET credits = :credits WHERE id = :id');
            $update->execute([
                ':credits' => $newCredits,
                ':id' => $user['id'],
            ]);

            $pdo->commit();

            $leaders = getLeaders($pdo);

            echo json_encode([
                'number' => $number,
                'color' => $color,
                'payout' => $payout,
                'totalBet' => $totalBet,
                'net' => $payout - $totalBet,
                'betsCount' => count($cleanBets),
                'credits' => $newCredits,
                'leaders' => $leaders,
            ]);
            break;

        case 'reset':
            $pdo->beginTransaction();
            $reset = $pdo->prepare('UPDATE users SET credits = 10000 WHERE id = :id');
            $reset->execute([':id' => $user['id']]);
            $pdo->commit();
            $leaders = getLeaders($pdo);
            echo json_encode(['credits' => 10000, 'leaders' => $leaders]);
            break;

        case 'leaders':
            echo json_encode(['leaders' => getLeaders($pdo)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
