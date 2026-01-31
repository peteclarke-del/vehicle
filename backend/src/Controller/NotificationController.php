<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Consumable;
use App\Entity\Todo;
use App\Entity\User;
use App\Entity\RoadTax;
use App\Entity\UserPreference;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Trait\UserSecurityTrait;

#[Route('/api/notifications')]

/**
 * class NotificationController
 */
class NotificationController extends AbstractController
{
    use UserSecurityTrait;

    private const DEFAULT_SETTINGS = [
        'dueSoonDays' => 30,
        'todoDueSoonDays' => 30,
        'serviceDueSoonDays' => 30,
        'consumableDueSoonMiles' => 1000,
        'refreshMinutes' => 10,
    ];

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param JWTEncoderInterface $jwtEncoder
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTEncoderInterface $jwtEncoder,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]

    /**
     * function list
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        return $this->json($this->buildNotifications($user));
    }

    #[Route('/stream', name: 'api_notifications_stream', methods: ['GET'])]

    /**
     * function streamNotifications
     *
     * @param Request $request
     *
     * @return StreamedResponse|JsonResponse
     */
    public function streamNotifications(Request $request): StreamedResponse|JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $settings = $this->getNotificationSettings($user);
        $intervalSeconds = max(10, min(300, (int) $settings['refreshMinutes'] * 60));

        $response = new StreamedResponse(
            function () use ($user, $intervalSeconds) {
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', '0');
                @ini_set('implicit_flush', '1');
                @set_time_limit(0);

                $lastPayload = null;

                while (true) {
                    if (connection_aborted()) {
                        break;
                    }

                    $payload = $this->buildNotifications($user);
                    $encoded = json_encode($payload);

                    if ($encoded !== $lastPayload) {
                        echo "event: notifications\n";
                        echo "data: {$encoded}\n\n";
                        $lastPayload = $encoded;
                    } else {
                        echo "event: ping\n";
                        echo "data: {}\n\n";
                    }

                    @ob_flush();
                    @flush();
                    sleep($intervalSeconds);
                }
            }
        );

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * function resolveUser
     *
     * @param Request $request
     *
     * @return User
     */
    private function resolveUser(Request $request): ?User
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        $token = $request->query->get('token');
        if (!$token) {
            return null;
        }

        try {
            $payload = $this->jwtEncoder->decode((string) $token);
            $identifier = $payload['username'] ?? $payload['email'] ?? null;
            $userId = $payload['id'] ?? $payload['user_id'] ?? null;

            $repo = $this->entityManager->getRepository(User::class);
            if ($identifier) {
                $found = $repo->findOneBy(['email' => $identifier]);
                if ($found instanceof User) {
                    return $found;
                }
            }

            if ($userId) {
                $found = $repo->find($userId);
                if ($found instanceof User) {
                    return $found;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Failed to decode JWT for notifications stream',
                ['error' => $e->getMessage()]
            );
        }

        return null;
    }

    /**
     * function getNotificationSettings
     *
     * @param User $user
     *
     * @return array
     */
    private function getNotificationSettings(User $user): array
    {
        $repo = $this->entityManager->getRepository(UserPreference::class);
        $pref = $repo->findOneBy([
            'user' => $user,
            'name' => 'notifications.settings',
        ]);

        if (!$pref) {
            return self::DEFAULT_SETTINGS;
        }

        $value = $pref->getValue();
        if ($value === null) {
            return self::DEFAULT_SETTINGS;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return self::DEFAULT_SETTINGS;
        }

        return array_merge(self::DEFAULT_SETTINGS, $decoded);
    }

    /**
     * function buildNotifications
     *
     * @param User $user
     *
     * @return array
     */
    private function buildNotifications(User $user): array
    {
        $settings = $this->getNotificationSettings($user);
        $dueSoonDays = (int) (
            $settings['dueSoonDays'] ?? self::DEFAULT_SETTINGS['dueSoonDays']
        );
        $todoDueSoonDays = (int) (
            $settings['todoDueSoonDays'] ?? self::DEFAULT_SETTINGS['todoDueSoonDays']
        );
        $serviceDueSoonDays = $settings['serviceDueSoonDays']
            ?? self::DEFAULT_SETTINGS['serviceDueSoonDays'];
        $serviceDueSoonDays = (int) $serviceDueSoonDays;
        $consumableDueSoonMiles = (int) (
            $settings['consumableDueSoonMiles']
            ?? self::DEFAULT_SETTINGS['consumableDueSoonMiles']
        );

        $today = new \DateTimeImmutable('today');
        $dueSoonDate = $today->modify('+' . $dueSoonDays . ' days');
        $serviceDueSoonDate = $today->modify('+' . $serviceDueSoonDays . ' days');

        $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);

        // Eager load vehicles with all required relationships to avoid N+1 queries
        $qb = $this->entityManager->createQueryBuilder()
            ->select('v', 'rt', 'mot', 'sr')
            ->from(Vehicle::class, 'v')
            ->leftJoin('v.roadTaxRecords', 'rt')
            ->leftJoin('v.motRecords', 'mot')
            ->leftJoin('v.serviceRecords', 'sr');

        if ($this->isAdminForUser($user)) {
            // Admin sees all vehicles
        } else {
            $qb->where('v.owner = :user')->setParameter('user', $user);
        }

        $vehicles = $qb->getQuery()->getResult();

        $vehicles = array_values(array_filter(
            $vehicles,
            static fn (Vehicle $vehicle): bool => strtolower((string) $vehicle->getStatus()) === 'live'
        ));

        if (empty($vehicles)) {
            return [];
        }

        $notifications = [];
        $country = $user->getCountry();

        foreach ($vehicles as $vehicle) {
            $vehicleId = (int) $vehicle->getId();
            $vehicleName = $this->buildVehicleLabel($vehicle);
            $vehicleKey = 'vehicle-' . $vehicleId;

            if ($country === 'GB' && !$vehicle->isMotExempt()) {
                $motDate = $vehicle->getMotExpiryDate();
                $notificationId = $vehicleKey . '-mot';

                if (!$motDate) {
                    $notifications[] = [
                        'id' => $notificationId,
                        'vehicleId' => $vehicleId,
                        'vehicleName' => $vehicleName,
                        'type' => 'mot',
                        'severity' => 'error',
                        'titleKey' => 'notifications.motExpired',
                        'messageKey' => 'notifications.motExpiredMessage',
                        'params' => ['date' => 'Unknown'],
                        'date' => null,
                    ];
                } else {
                    $motDate = \DateTimeImmutable::createFromInterface($motDate)
                        ->setTime(0, 0, 0);
                    if ($motDate < $today) {
                        $notifications[] = [
                            'id' => $notificationId,
                            'vehicleId' => $vehicleId,
                            'vehicleName' => $vehicleName,
                            'type' => 'mot',
                            'severity' => 'error',
                            'titleKey' => 'notifications.motExpired',
                            'messageKey' => 'notifications.motExpiredMessage',
                            'params' => ['date' => $this->formatDate($motDate)],
                            'date' => $this->formatDate($motDate),
                        ];
                    } elseif ($motDate <= $dueSoonDate) {
                        $diffSeconds = $motDate->getTimestamp() - $today->getTimestamp();
                        $daysUntil = (int) ceil($diffSeconds / 86400);
                        $notifications[] = [
                            'id' => $notificationId,
                            'vehicleId' => $vehicleId,
                            'vehicleName' => $vehicleName,
                            'type' => 'mot',
                            'severity' => 'warning',
                            'titleKey' => 'notifications.motExpiringSoon',
                            'messageKey' => 'notifications.motExpiringMessage',
                            'params' => [
                                'days' => $daysUntil,
                                'plural' => $daysUntil !== 1 ? 's' : '',
                            ],
                            'date' => $this->formatDate($motDate),
                        ];
                    }
                }
            }

            if ($country === 'GB' && !$vehicle->isRoadTaxExempt()) {
                $notificationId = $vehicleKey . '-tax';
                $latestTax = $this->getLatestRoadTaxRecord($vehicle);
                $startDate = $latestTax?->getStartDate();
                $expiryDate = $latestTax?->getExpiryDate();

                if ($startDate && $expiryDate) {
                    $startDate = \DateTimeImmutable::createFromInterface($startDate)
                        ->setTime(0, 0, 0);
                    $expiryDate = \DateTimeImmutable::createFromInterface($expiryDate)
                        ->setTime(0, 0, 0);

                    $isCurrent = $startDate <= $today && $expiryDate >= $today;
                    if ($isCurrent && $expiryDate <= $dueSoonDate) {
                        $diffSeconds = $expiryDate->getTimestamp() - $today->getTimestamp();
                        $daysUntil = (int) ceil($diffSeconds / 86400);
                        $notifications[] = [
                            'id' => $notificationId,
                            'vehicleId' => $vehicleId,
                            'vehicleName' => $vehicleName,
                            'type' => 'tax',
                            'severity' => 'warning',
                            'titleKey' => 'notifications.roadTaxExpiringSoon',
                            'messageKey' => 'notifications.roadTaxExpiringMessage',
                            'params' => [
                                'days' => $daysUntil,
                                'plural' => $daysUntil !== 1 ? 's' : '',
                            ],
                            'date' => $this->formatDate($expiryDate),
                        ];
                    }
                }
            }

            $insuranceDate = $vehicle->getInsuranceExpiryDate();
            $insuranceId = $vehicleKey . '-insurance';
            if (!$insuranceDate) {
                $notifications[] = [
                    'id' => $insuranceId,
                    'vehicleId' => $vehicleId,
                    'vehicleName' => $vehicleName,
                    'type' => 'insurance',
                    'severity' => 'error',
                    'titleKey' => 'notifications.insuranceExpired',
                    'messageKey' => 'notifications.insuranceExpiredMessage',
                    'params' => ['date' => 'Unknown'],
                    'date' => null,
                ];
            } else {
                $insuranceDate = \DateTimeImmutable::createFromInterface(
                    $insuranceDate
                )->setTime(0, 0, 0);
                if ($insuranceDate < $today) {
                    $notifications[] = [
                        'id' => $insuranceId,
                        'vehicleId' => $vehicleId,
                        'vehicleName' => $vehicleName,
                        'type' => 'insurance',
                        'severity' => 'error',
                        'titleKey' => 'notifications.insuranceExpired',
                        'messageKey' => 'notifications.insuranceExpiredMessage',
                        'params' => ['date' => $this->formatDate($insuranceDate)],
                        'date' => $this->formatDate($insuranceDate),
                    ];
                } elseif ($insuranceDate <= $dueSoonDate) {
                    $diffSeconds = $insuranceDate->getTimestamp() - $today->getTimestamp();
                    $daysUntil = (int) ceil($diffSeconds / 86400);
                    $notifications[] = [
                        'id' => $insuranceId,
                        'vehicleId' => $vehicleId,
                        'vehicleName' => $vehicleName,
                        'type' => 'insurance',
                        'severity' => 'warning',
                        'titleKey' => 'notifications.insuranceExpiringSoon',
                        'messageKey' => 'notifications.insuranceExpiringMessage',
                        'params' => [
                            'days' => $daysUntil,
                            'plural' => $daysUntil !== 1 ? 's' : '',
                        ],
                        'date' => $this->formatDate($insuranceDate),
                    ];
                }
            }

            if ($vehicle->getServiceIntervalMonths()) {
                $serviceId = $vehicleKey . '-service';
                $lastService = $vehicle->getLastServiceDate();

                if (!$lastService) {
                    $notifications[] = [
                        'id' => $serviceId,
                        'vehicleId' => $vehicleId,
                        'vehicleName' => $vehicleName,
                        'type' => 'service',
                        'severity' => 'warning',
                        'titleKey' => 'notifications.serviceOverdue',
                        'messageKey' => 'notifications.serviceNoHistoryMessage',
                        'params' => [],
                        'date' => null,
                    ];
                } else {
                    $intervalMonths = (int) $vehicle->getServiceIntervalMonths();
                    $dueDate = \DateTimeImmutable::createFromInterface($lastService)
                        ->modify('+' . $intervalMonths . ' months')
                        ->setTime(0, 0, 0);

                    if ($dueDate < $today) {
                        $diffSeconds = $today->getTimestamp() - $dueDate->getTimestamp();
                        $monthsOverdue = max(1, (int) floor($diffSeconds / (86400 * 30.44)));
                        $notifications[] = [
                            'id' => $serviceId,
                            'vehicleId' => $vehicleId,
                            'vehicleName' => $vehicleName,
                            'type' => 'service',
                            'severity' => 'warning',
                            'titleKey' => 'notifications.serviceOverdue',
                            'messageKey' => 'notifications.serviceOverdueMessage',
                            'params' => ['months' => $monthsOverdue],
                            'date' => $this->formatDate($lastService),
                        ];
                    } elseif ($dueDate <= $serviceDueSoonDate) {
                        $diffSeconds = $dueDate->getTimestamp() - $today->getTimestamp();
                        $daysUntil = (int) ceil($diffSeconds / 86400);
                        $notifications[] = [
                            'id' => $serviceId,
                            'vehicleId' => $vehicleId,
                            'vehicleName' => $vehicleName,
                            'type' => 'service',
                            'severity' => 'warning',
                            'titleKey' => 'notifications.serviceDueSoon',
                            'messageKey' => 'notifications.serviceDueSoonMessage',
                            'params' => [
                                'days' => $daysUntil,
                                'plural' => $daysUntil !== 1 ? 's' : '',
                            ],
                            'date' => $this->formatDate($lastService),
                        ];
                    }
                }
            }
        }

        // Eager load consumables with their types to avoid N+1
        $consumables = $this->entityManager->getRepository(Consumable::class)
            ->createQueryBuilder('c')
            ->select('c', 'ct', 'v')
            ->leftJoin('c.consumableType', 'ct')
            ->leftJoin('c.vehicle', 'v')
            ->where('c.vehicle IN (:vehicles)')
            ->setParameter('vehicles', $vehicles)
            ->getQuery()
            ->getResult();

        foreach ($consumables as $consumable) {
            $vehicle = $consumable->getVehicle();
            if (!$vehicle instanceof Vehicle) {
                continue;
            }

            $vehicleName = $this->buildVehicleLabel($vehicle);

            $currentMileage = $vehicle->getCurrentMileage();
            if (!$currentMileage) {
                continue;
            }

            $nextReplacementMileage = $consumable->getNextReplacementMileage()
                ?? $consumable->calculateNextReplacementMileage();
            if (!$nextReplacementMileage) {
                continue;
            }

            $milesUntil = (int) ($nextReplacementMileage - $currentMileage);
            $itemName = $consumable->getConsumableType()?->getName()
                ?? $consumable->getDescription()
                ?? 'Consumable';
            $notificationId = 'consumable-' . $consumable->getId();

            if ($milesUntil <= 0) {
                $notifications[] = [
                    'id' => $notificationId,
                    'vehicleId' => (int) $vehicle->getId(),
                    'vehicleName' => $vehicleName,
                    'type' => 'consumable',
                    'severity' => 'error',
                    'titleKey' => 'notifications.consumableOverdue',
                    'messageKey' => 'notifications.consumableOverdueMessage',
                    'params' => ['item' => $itemName],
                    'route' => '/consumables',
                ];
            } elseif ($milesUntil <= $consumableDueSoonMiles) {
                $notifications[] = [
                    'id' => $notificationId,
                    'vehicleId' => (int) $vehicle->getId(),
                    'vehicleName' => $vehicleName,
                    'type' => 'consumable',
                    'severity' => 'warning',
                    'titleKey' => 'notifications.consumableDueSoon',
                    'messageKey' => 'notifications.consumableDueSoonMessage',
                    'params' => ['item' => $itemName, 'miles' => $milesUntil],
                    'route' => '/consumables',
                ];
            }
        }

        // Eager load todos with vehicles
        $todos = $this->entityManager->getRepository(Todo::class)
            ->createQueryBuilder('t')
            ->select('t', 'v')
            ->leftJoin('t.vehicle', 'v')
            ->where('t.vehicle IN (:vehicles)')
            ->andWhere('t.done = :done')
            ->setParameter('vehicles', $vehicles)
            ->setParameter('done', false)
            ->getQuery()
            ->getResult();

        foreach ($todos as $todo) {
            $dueDate = $todo->getDueDate();
            if (!$dueDate) {
                continue;
            }

            $dueDate = \DateTimeImmutable::createFromInterface(
                $dueDate
            )->setTime(0, 0, 0);
            $diffSeconds = $dueDate->getTimestamp() - $today->getTimestamp();
            $daysUntil = (int) ceil($diffSeconds / 86400);
            $vehicle = $todo->getVehicle();
            if (!$vehicle instanceof Vehicle) {
                continue;
            }
            $vehicleName = $this->buildVehicleLabel($vehicle);
            $notificationId = 'todo-' . $todo->getId();

            if ($daysUntil < 0) {
                $notifications[] = [
                    'id' => $notificationId,
                    'vehicleId' => (int) $vehicle->getId(),
                    'vehicleName' => $vehicleName,
                    'type' => 'todo',
                    'severity' => 'error',
                    'titleKey' => 'notifications.todoOverdue',
                    'messageKey' => 'notifications.todoOverdueMessage',
                    'params' => ['title' => $todo->getTitle()],
                    'route' => '/todo',
                ];
            } elseif ($daysUntil <= $todoDueSoonDays) {
                $notifications[] = [
                    'id' => $notificationId,
                    'vehicleId' => (int) $vehicle->getId(),
                    'vehicleName' => $vehicleName,
                    'type' => 'todo',
                    'severity' => 'warning',
                    'titleKey' => 'notifications.todoDueSoon',
                    'messageKey' => 'notifications.todoDueSoonMessage',
                    'params' => [
                        'title' => $todo->getTitle(),
                        'days' => $daysUntil,
                        'plural' => $daysUntil !== 1 ? 's' : '',
                    ],
                    'route' => '/todo',
                ];
            }
        }

        return $notifications;
    }

    /**
     * function formatDate
     *
     * @param \DateTimeInterface $date
     *
     * @return string
     */
    private function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * function getLatestRoadTaxRecord
     *
     * @param Vehicle $vehicle
     *
     * @return RoadTax
     */
    private function getLatestRoadTaxRecord(Vehicle $vehicle): ?RoadTax
    {
        $latest = null;
        $latestStart = null;

        foreach ($vehicle->getRoadTaxRecords() as $record) {
            $startDate = $record->getStartDate();
            if (!$startDate) {
                continue;
            }
            if ($latestStart === null || $startDate > $latestStart) {
                $latestStart = $startDate;
                $latest = $record;
            }
        }

        return $latest;
    }

    /**
     * function buildVehicleLabel
     *
     * @param Vehicle $vehicle
     *
     * @return string
     */
    private function buildVehicleLabel(Vehicle $vehicle): string
    {
        $make = trim((string) $vehicle->getMake());
        $model = trim((string) $vehicle->getModel());
        $name = trim((string) ($vehicle->getName() ?? ''));
        $registration = trim((string) ($vehicle->getRegistrationNumber() ?? ''));

        $label = trim($make . ' ' . $model);
        if ($label === '') {
            $label = $name;
        }

        if ($registration !== '') {
            $label = $label !== '' ? $label . ' (' . $registration . ')' : $registration;
        }

        return $label !== '' ? $label : $name;
    }
}
