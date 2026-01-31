<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Report;
use App\Entity\Vehicle;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Route('/api/reports')]
class ReportsController extends AbstractController
{
    private function disableProfiler(): void
    {
        if (isset($this->container) && $this->container->has('profiler')) {
            try {
                $profiler = $this->container->get('profiler');
                if (is_object($profiler) && method_exists($profiler, 'disable')) {
                    $profiler->disable();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    #[Route('', name: 'api_reports_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->disableProfiler();
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $repo = $em->getRepository(Report::class);
        $items = $repo->findBy(['user' => $user], ['generatedAt' => 'DESC']);
        $out = [];
        foreach ($items as $r) {
            $out[] = [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'template' => $r->getTemplateKey(),
                'payload' => $r->getPayload(),
                'vehicleId' => $r->getVehicleId(),
                'generatedAt' => $r->getGeneratedAt()->format(DATE_ATOM),
            ];
        }

        return $this->json($out);
    }

    #[Route('', name: 'api_reports_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->disableProfiler();
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }
        $data = json_decode($request->getContent(), true) ?: [];

        // Require frontend to include the template JSON in the payload.
        if (empty($data['templateContent']) || !is_array($data['templateContent'])) {
            return $this->json(['error' => 'templateContent is required in the request payload'], 422);
        }

        $report = new Report();
        $report->setUser($user);
        $report->setName((string)($data['name'] ?? ($data['template'] ?? 'Report')));
        $report->setTemplateKey($data['template'] ?? null);
        $report->setVehicleId(isset($data['vehicleId']) && $data['vehicleId'] !== null ? (int)$data['vehicleId'] : null);

        // Store entire incoming payload so we can regenerate later. This will include
        // `templateContent` when the frontend provides it (runtime-editable templates).
        $report->setPayload($data ?: null);
        $report->setGeneratedAt(new \DateTime());

        $em->persist($report);
        $em->flush();

        return $this->json([
            'id' => $report->getId(),
            'name' => $report->getName(),
            'template' => $report->getTemplateKey(),
            'vehicleId' => $report->getVehicleId(),
            'generatedAt' => $report->getGeneratedAt()->format(DATE_ATOM),
        ], 201);
    }

    #[Route('/{id}/download', name: 'api_reports_download', methods: ['GET'])]
    public function download(int|string $id, EntityManagerInterface $em): Response
    {
        $this->disableProfiler();
        $repo = $em->getRepository(Report::class);
        $r = $repo->find((int)$id);
        if (!$r) {
            return new Response('Not found', 404);
        }

        $payload = $r->getPayload() ?? [];
        $template = $payload['templateContent'] ?? null;

        // Enforce: backend must not read frontend source files for templates.
        // Require persisted reports to include `templateContent` in their payload.
        if (!is_array($template)) {
            return new Response('Persisted report missing templateContent; backend requires templateContent to be present. Recreate the report including the template JSON.', 422);
        }

        // Determine requested file type from template outputs, default to PDF
        $fileType = 'pdf';
        if (is_array($template) && isset($template['outputs']) && is_array($template['outputs'])) {
            foreach ($template['outputs'] as $o) {
                if (isset($o['mode']) && $o['mode'] === 'file' && !empty($o['fileType'])) {
                    $fileType = $o['fileType'];
                    break;
                }
            }
        }

        // Gather simple table data for first table element (basic implementation)
        $rows = [];
        $headers = [];
        if (is_array($template) && (isset($template['sheets']) || (isset($template['elements']) && is_array($template['elements'])))) {
            foreach ($template['elements'] ?? [] as $el) {
                if (($el['type'] ?? '') === 'table') {
                    $cols = $el['columns'] ?? [];
                    $headers = array_map(function ($c) {
                        return $c['label'] ?? ($c['key'] ?? '');
                    }, $cols);

                    // map source to rows using entity repositories (no SQL allowed in JSON)
                    $source = $el['source'] ?? '';
                    $rows = [];
                    if ($r->getVehicleId()) {
                        // Eager load vehicle with all collections to avoid N+1
                        $v = $em->createQueryBuilder()
                            ->select('v', 'fr', 'p', 'c', 'sr')
                            ->from(Vehicle::class, 'v')
                            ->leftJoin('v.fuelRecords', 'fr')
                            ->leftJoin('v.parts', 'p')
                            ->leftJoin('v.consumables', 'c')
                            ->leftJoin('v.serviceRecords', 'sr')
                            ->where('v.id = :vehicleId')
                            ->setParameter('vehicleId', $r->getVehicleId())
                            ->getQuery()
                            ->getOneOrNullResult();

                        if ($v) {
                            switch ($source) {
                                case 'parts':
                                    foreach ($v->getParts() as $p) {
                                        $rows[] = ['date' => $p->getPurchaseDate()?->format('Y-m-d') ?? '', 'item' => $p->getDescription(), 'cost' => $p->getCost()];
                                    }
                                    break;
                                case 'serviceRecords':
                                    foreach ($v->getServiceRecords() as $srec) {
                                        $rows[] = ['date' => $srec->getServiceDate()?->format('Y-m-d') ?? '', 'item' => $srec->getServiceType(), 'cost' => $srec->getLaborCost()];
                                    }
                                    break;
                                case 'fuelRecords':
                                    foreach ($v->getFuelRecords() as $fr) {
                                        $rows[] = ['date' => $fr->getDate()?->format('Y-m-d') ?? '', 'litres' => $fr->getLitres(), 'cost' => $fr->getCost(), 'mileage' => $fr->getMileage(), 'id' => $fr->getId()];
                                    }
                                    break;
                                case 'consumables':
                                    foreach ($v->getConsumables() as $c) {
                                        $rows[] = ['date' => $c->getLastChanged()?->format('Y-m-d') ?? '', 'item' => $c->getDescription(), 'cost' => $c->getCost()];
                                    }
                                    break;
                                case 'vehicles':
                                    $rows[] = ['registration' => $v->getRegistrationNumber(), 'make' => $v->getMake(), 'model' => $v->getModel()];
                                    break;
                            }
                        }
                    } else {
                        // no vehicle filter: fetch from repositories
                        switch ($source) {
                            case 'parts':
                                $entities = $em->createQueryBuilder()
                                    ->select('p', 'v')
                                    ->from(Part::class, 'p')
                                    ->leftJoin('p.vehicle', 'v')
                                    ->setMaxResults(10000)
                                    ->getQuery()
                                    ->getResult();
                                foreach ($entities as $p) {
                                    $rows[] = ['date' => $p->getPurchaseDate()?->format('Y-m-d') ?? '', 'item' => $p->getDescription(), 'cost' => $p->getCost(), 'vehicle' => $p->getVehicle()?->getId()];
                                }
                                break;
                            case 'serviceRecords':
                                $entities = $em->createQueryBuilder()
                                    ->select('sr', 'v')
                                    ->from(ServiceRecord::class, 'sr')
                                    ->leftJoin('sr.vehicle', 'v')
                                    ->setMaxResults(10000)
                                    ->getQuery()
                                    ->getResult();
                                foreach ($entities as $srec) {
                                    $rows[] = ['date' => $srec->getServiceDate()?->format('Y-m-d') ?? '', 'item' => $srec->getServiceType(), 'cost' => $srec->getLaborCost(), 'vehicle' => $srec->getVehicle()?->getId()];
                                }
                                break;
                            case 'fuelRecords':
                                $entities = $em->createQueryBuilder()
                                    ->select('fr', 'v')
                                    ->from(FuelRecord::class, 'fr')
                                    ->leftJoin('fr.vehicle', 'v')
                                    ->setMaxResults(10000)
                                    ->getQuery()
                                    ->getResult();
                                foreach ($entities as $fr) {
                                    $rows[] = ['date' => $fr->getDate()?->format('Y-m-d') ?? '', 'litres' => $fr->getLitres(), 'cost' => $fr->getCost(), 'mileage' => $fr->getMileage(), 'vehicle' => $fr->getVehicle()?->getId(), 'id' => $fr->getId()];
                                }
                                break;
                            case 'consumables':
                                $entities = $em->createQueryBuilder()
                                    ->select('c', 'v')
                                    ->from(Consumable::class, 'c')
                                    ->leftJoin('c.vehicle', 'v')
                                    ->setMaxResults(10000)
                                    ->getQuery()
                                    ->getResult();
                                foreach ($entities as $c) {
                                    $rows[] = ['date' => $c->getLastChanged()?->format('Y-m-d') ?? '', 'item' => $c->getDescription(), 'cost' => $c->getCost(), 'vehicle' => $c->getVehicle()?->getId()];
                                }
                                break;
                            case 'vehicles':
                                $entities = $em->createQueryBuilder()
                                    ->select('v')
                                    ->from(Vehicle::class, 'v')
                                    ->setMaxResults(10000)
                                    ->getQuery()
                                    ->getResult();
                                foreach ($entities as $v) {
                                    $rows[] = ['registration' => $v->getRegistrationNumber(), 'make' => $v->getMake(), 'model' => $v->getModel(), 'id' => $v->getId()];
                                }
                                break;
                        }
                    }
                    break; // only first table for now
                }
            }
        }
            // Generic XLSX generation driven entirely by `templateContent`.
        if ($fileType === 'xlsx' && is_array($template)) {
            try {
                $conn = $em->getConnection();

                // Normalize sheets: prefer explicit 'sheets', else fall back to first table element
                $sheets = $template['sheets'] ?? null;
                if (!$sheets && !empty($template['elements'])) {
                    // build a single sheet from first table-like element
                    foreach ($template['elements'] as $el) {
                        if (($el['type'] ?? '') === 'table') {
                            $sheets = [[
                                'name' => $el['name'] ?? ($template['name'] ?? 'Report'),
                                'columns' => $el['columns'] ?? [],
                                'source' => $el['source'] ?? null,
                            ]];
                            break;
                        }
                    }
                }

                if (!empty($sheets) && is_array($sheets)) {
                    $spreadsheet = new Spreadsheet();
                    $sheetIndex = 0;

                    $alpha = function ($n) {
                        $letters = '';
                        while ($n > 0) {
                            $mod = ($n - 1) % 26;
                            $letters = chr(65 + $mod) . $letters;
                            $n = (int)(($n - $mod) / 26);
                        }
                        return $letters;
                    };

                    foreach ($sheets as $si => $sdef) {
                        $sheet = ($sheetIndex === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
                        $title = $sdef['name'] ?? ($template['name'] ?? ('Sheet' . ($si + 1)));
                        $sheet->setTitle(substr((string)$title, 0, 31));

                        $cols = $sdef['columns'] ?? [];
                        $rows = [];

                        // report-level params
                        $reportParams = [];
                        if ($r->getVehicleId()) {
                            $reportParams['vehicle_id'] = $r->getVehicleId();
                        }
                        $periodFrom = $payload['period']['from'] ?? $payload['from'] ?? null;
                        $periodTo = $payload['period']['to'] ?? $payload['to'] ?? null;
                        if ($periodFrom) {
                            $reportParams['from'] = $periodFrom;
                        }
                        if ($periodTo) {
                            $reportParams['to'] = $periodTo;
                        }

                        // fetch rows from data / query / source
                        if (!empty($sdef['data']) && is_array($sdef['data'])) {
                            $rows = $sdef['data'];
                        } elseif (!empty($sdef['query'])) {
                            $query = $sdef['query'];
                            $params = array_merge($reportParams, $sdef['params'] ?? []);
                            $stmt = $conn->executeQuery($query, $params);
                            $rows = $stmt->fetchAllAssociative();
                        } elseif (!empty($sdef['source'])) {
                            // entity/repository-driven mapping (no SQL execution)
                            $rows = [];
                            $source = $sdef['source'];
                            // if a vehicle filter is present, prefer using the Vehicle entity collections
                            if (!empty($reportParams['vehicle_id'])) {
                                try {
                                    // Eager load vehicle with all collections to avoid N+1
                                    $v = $em->createQueryBuilder()
                                        ->select('v', 'fr', 'p', 'c', 'sr', 'mr')
                                        ->from(Vehicle::class, 'v')
                                        ->leftJoin('v.fuelRecords', 'fr')
                                        ->leftJoin('v.parts', 'p')
                                        ->leftJoin('v.consumables', 'c')
                                        ->leftJoin('v.serviceRecords', 'sr')
                                        ->leftJoin('v.motRecords', 'mr')
                                        ->where('v.id = :vehicleId')
                                        ->setParameter('vehicleId', (int)$reportParams['vehicle_id'])
                                        ->getQuery()
                                        ->getOneOrNullResult();

                                    if ($v) {
                                        switch ($source) {
                                            case 'fuelRecords':
                                                foreach ($v->getFuelRecords() as $fr) {
                                                    $rows[] = [
                                                        'date' => $fr->getDate()?->format('Y-m-d') ?? '',
                                                        'mileage' => $fr->getMileage(),
                                                        'litres' => $fr->getLitres(),
                                                        'cost' => $fr->getCost(),
                                                        'id' => $fr->getId(),
                                                    ];
                                                }
                                                break;
                                            case 'parts':
                                                foreach ($v->getParts() as $p) {
                                                    $rows[] = [
                                                        'date' => $p->getPurchaseDate()?->format('Y-m-d') ?? '',
                                                        'item' => $p->getDescription(),
                                                        'cost' => $p->getCost(),
                                                    ];
                                                }
                                                break;
                                            case 'consumables':
                                                foreach ($v->getConsumables() as $c) {
                                                    $rows[] = [
                                                        'date' => $c->getLastChanged()?->format('Y-m-d') ?? '',
                                                        'item' => $c->getDescription(),
                                                        'cost' => $c->getCost(),
                                                    ];
                                                }
                                                break;
                                            case 'serviceRecords':
                                                foreach ($v->getServiceRecords() as $srec) {
                                                    $rows[] = [
                                                        'date' => $srec->getServiceDate()?->format('Y-m-d') ?? '',
                                                        'item' => $srec->getServiceType(),
                                                        'cost' => $srec->getLaborCost(),
                                                    ];
                                                }
                                                break;
                                            case 'mot':
                                            case 'motRecords':
                                                foreach ($v->getMotRecords() as $m) {
                                                    $rows[] = [
                                                        'date' => $m->getTestDate()?->format('Y-m-d') ?? '',
                                                        'item' => 'MOT ' . ($m->getResult() ?? ''),
                                                        'cost' => $m->getTotalCost(),
                                                    ];
                                                }
                                                break;
                                            case 'vehicles':
                                                $rows[] = ['registration' => $v->getRegistrationNumber(), 'make' => $v->getMake(), 'model' => $v->getModel(), 'id' => $v->getId()];
                                                break;
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // ignore entity fallback failures
                                }
                            } else {
                                // no vehicle filter: fetch from repositories directly
                                switch ($source) {
                                    case 'parts':
                                        $entities = $em->createQueryBuilder()
                                            ->select('p', 'v')
                                            ->from(Part::class, 'p')
                                            ->leftJoin('p.vehicle', 'v')
                                            ->setMaxResults(10000)
                                            ->getQuery()
                                            ->getResult();
                                        foreach ($entities as $p) {
                                            $rows[] = ['date' => $p->getPurchaseDate()?->format('Y-m-d') ?? '', 'item' => $p->getDescription(), 'cost' => $p->getCost(), 'vehicle' => $p->getVehicle()?->getId()];
                                        }
                                        break;
                                    case 'serviceRecords':
                                        $entities = $em->createQueryBuilder()
                                            ->select('sr', 'v')
                                            ->from(ServiceRecord::class, 'sr')
                                            ->leftJoin('sr.vehicle', 'v')
                                            ->setMaxResults(10000)
                                            ->getQuery()
                                            ->getResult();
                                        foreach ($entities as $srec) {
                                            $rows[] = ['date' => $srec->getServiceDate()?->format('Y-m-d') ?? '', 'item' => $srec->getServiceType(), 'cost' => $srec->getLaborCost(), 'vehicle' => $srec->getVehicle()?->getId()];
                                        }
                                        break;
                                    case 'fuelRecords':
                                        $entities = $em->createQueryBuilder()
                                            ->select('fr', 'v')
                                            ->from(FuelRecord::class, 'fr')
                                            ->leftJoin('fr.vehicle', 'v')
                                            ->setMaxResults(10000)
                                            ->getQuery()
                                            ->getResult();
                                        foreach ($entities as $fr) {
                                            $rows[] = ['date' => $fr->getDate()?->format('Y-m-d') ?? '', 'litres' => $fr->getLitres(), 'cost' => $fr->getCost(), 'mileage' => $fr->getMileage(), 'vehicle' => $fr->getVehicle()?->getId(), 'id' => $fr->getId()];
                                        }
                                        break;
                                    case 'consumables':
                                        $entities = $em->createQueryBuilder()
                                            ->select('c', 'v')
                                            ->from(Consumable::class, 'c')
                                            ->leftJoin('c.vehicle', 'v')
                                            ->setMaxResults(10000)
                                            ->getQuery()
                                            ->getResult();
                                        foreach ($entities as $c) {
                                            $rows[] = ['date' => $c->getLastChanged()?->format('Y-m-d') ?? '', 'item' => $c->getDescription(), 'cost' => $c->getCost(), 'vehicle' => $c->getVehicle()?->getId()];
                                        }
                                        break;
                                    case 'vehicles':
                                        $entities = $em->createQueryBuilder()
                                            ->select('v')
                                            ->from(Vehicle::class, 'v')
                                            ->setMaxResults(10000)
                                            ->getQuery()
                                            ->getResult();
                                        foreach ($entities as $v) {
                                            $rows[] = ['registration' => $v->getRegistrationNumber(), 'make' => $v->getMake(), 'model' => $v->getModel(), 'id' => $v->getId()];
                                        }
                                        break;
                                }
                            }
                        }

                        // If columns not provided but rows exist, infer from first row
                        if (empty($cols) && !empty($rows)) {
                            $first = $rows[0] ?? [];
                            $cols = [];
                            foreach (array_keys($first) as $k) {
                                $cols[] = ['key' => $k, 'label' => ucfirst(str_replace('_', ' ', $k))];
                            }
                        }

                        // Derived fields (e.g. mpg)
                        $derivedDefs = array_filter($cols, function ($c) {
                            return !empty($c['derived']);
                        });
                        if (!empty($derivedDefs) && !empty($rows)) {
                            // compute derived mpg: need rows sorted by date asc
                            usort($rows, function ($a, $b) {
                                return (strtotime($a['date'] ?? '') ?: 0) <=> (strtotime($b['date'] ?? '') ?: 0);
                            });
                            $prev = null;
                            foreach ($rows as $i => $rrow) {
                                foreach ($derivedDefs as $dcol) {
                                    if (($dcol['derived'] ?? '') === 'mpg') {
                                        $mpgKey = $dcol['key'] ?? $dcol['field'] ?? 'mpg';
                                        $mileage = isset($rrow['mileage']) ? (float)$rrow['mileage'] : null;
                                        $litres = isset($rrow['litres']) ? (float)$rrow['litres'] : null;
                                        $mpgVal = '';
                                        if ($prev && is_numeric($mileage) && is_numeric($prev['mileage']) && is_numeric($litres) && $litres > 0) {
                                            $delta = $mileage - $prev['mileage'];
                                            if ($delta > 0) {
                                                $mpgVal = round($delta / $litres, 2);
                                            }
                                        }
                                        $rows[$i][$mpgKey] = $mpgVal !== '' ? number_format($mpgVal, 2) : '';
                                    }
                                }
                                $prev = $rrow;
                            }
                        }

                        // Sorting: per-column or sheet-level
                        $sortDefs = [];
                        foreach ($cols as $c) {
                            if (!empty($c['sort'])) {
                                $sortDefs[] = ['key' => $c['key'] ?? $c['field'] ?? null, 'dir' => strtolower($c['sort'])];
                            }
                        }
                        if (empty($sortDefs) && !empty($sdef['sort'])) {
                            $sortDefs[] = ['key' => $sdef['sort']['field'] ?? null, 'dir' => strtolower($sdef['sort']['order'] ?? ($sdef['sort']['dir'] ?? 'asc'))];
                        }
                        if (!empty($sortDefs) && !empty($rows)) {
                            usort($rows, function ($a, $b) use ($sortDefs) {
                                foreach ($sortDefs as $sd) {
                                    $k = $sd['key'];
                                    $dir = ($sd['dir'] === 'desc') ? -1 : 1;
                                    $av = $a[$k] ?? null;
                                    $bv = $b[$k] ?? null;
                                    if (is_numeric($av) && is_numeric($bv)) {
                                        if ($av < $bv) {
                                            return -1 * $dir;
                                        }
                                        if ($av > $bv) {
                                            return 1 * $dir;
                                        }
                                    } else {
                                        $cmp = strcmp((string)$av, (string)$bv);
                                        if ($cmp !== 0) {
                                            return $cmp * $dir;
                                        }
                                    }
                                }
                                return 0;
                            });
                        }

                        // Aggregations: compute per-column aggregates after rows ready
                        $aggregates = [];
                        foreach ($cols as $ci => $c) {
                            if (!empty($c['aggregate'])) {
                                $aggregates[$ci] = $c['aggregate'];
                            }
                        }

                        // write headers
                        $colNum = 1;
                        foreach ($cols as $c) {
                            $colLetter = $alpha($colNum);
                            $sheet->setCellValue($colLetter . '1', $c['label'] ?? ($c['key'] ?? ''));
                            if (!empty($c['width'])) {
                                $sheet->getColumnDimension($colLetter)->setWidth((float)$c['width']);
                            }
                            $colNum++;
                        }
                        // header style
                        $firstCol = $alpha(1);
                        $lastCol = $alpha(max(1, count($cols)));
                        $sheet->getStyle($firstCol . '1:' . $lastCol . '1')->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDEDED']],
                        ]);

                        // write rows
                        $rnum = 2;
                        foreach ($rows as $row) {
                            $colNum = 1;
                            foreach ($cols as $c) {
                                $colLetter = $alpha($colNum);
                                $key = $c['key'] ?? $c['field'] ?? null;
                                $val = $key !== null && is_array($row) && array_key_exists($key, $row) ? $row[$key] : ($row[$colNum - 1] ?? '');
                                $coord = $colLetter . $rnum;
                                if (!empty($c['format']) && $c['format'] === 'currency') {
                                    $num = is_numeric($val) ? (float)$val : (float)preg_replace('/[^0-9\.\-]/', '', (string)$val);
                                    $sheet->setCellValue($coord, $num);
                                    $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('"\u00A3"#,##0.00');
                                } else {
                                    $sheet->setCellValue($coord, (string)$val);
                                }
                                if (!empty($c['alignment'])) {
                                    $align = strtolower($c['alignment']);
                                    $sheet->getStyle($coord)->getAlignment()->setHorizontal($align === 'right' ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT : ($align === 'center' ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER : \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT));
                                }
                                $colNum++;
                            }
                            $rnum++;
                        }

                        // write aggregate row if requested
                        if (!empty($aggregates)) {
                            $aggRow = [];
                            foreach ($cols as $ci => $c) {
                                $key = $c['key'] ?? $c['field'] ?? null;
                                if (isset($aggregates[$ci]) && !empty($key)) {
                                    $type = $aggregates[$ci];
                                    $vals = array_column($rows, $key);
                                    $numeric = array_filter($vals, 'is_numeric');
                                    if ($type === 'sum') {
                                        $agg = array_sum($numeric);
                                    } elseif ($type === 'avg') {
                                        $agg = count($numeric) ? array_sum($numeric) / count($numeric) : 0;
                                    } elseif ($type === 'count') {
                                        $agg = count($vals);
                                    } else {
                                        $agg = '';
                                    }
                                    $aggRow[] = $agg;
                                } else {
                                    $aggRow[] = '';
                                }
                            }
                            // place label in first column
                            if (!empty($aggRow)) {
                                $sheet->setCellValue($alpha(1) . $rnum, 'Totals');
                                foreach ($aggRow as $ci => $v) {
                                    $colLetter = $alpha($ci + 1);
                                    $sheet->setCellValue($colLetter . $rnum, (string)$v);
                                }
                                $rnum++;
                            }
                        }

                        $sheetIndex++;
                    }

                        // Write XLSX to memory and return
                        $writer = new Xlsx($spreadsheet);
                        $tmp = fopen('php://temp', 'r+');
                        $writer->save($tmp);
                        rewind($tmp);
                        $content = stream_get_contents($tmp);
                        fclose($tmp);

                        $resp = new Response($content);
                        $resp->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                        $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.xlsx"', (string)$r->getId()));
                        return $resp;
                }
            } catch (\Throwable $e) {
                // fall through to other handlers; leave fallback CSV/PDF in place
            }
        }

        if ($fileType === 'csv') {
            $fh = fopen('php://temp', 'r+');
            if ($headers) {
                fputcsv($fh, $headers);
            }
            foreach ($rows as $rrow) {
                // convert associative row to ordered values matching headers where possible
                $out = [];
                if ($headers) {
                    foreach ($headers as $h) {
                        // try to map header back to key (best effort)
                        $key = null;
                        // naive: lowercased header
                        $k = strtolower(str_replace(' ', '_', $h));
                        if (array_key_exists($k, $rrow)) {
                            $key = $k;
                        } else {
                            // try to use first value
                            $key = array_key_first($rrow);
                        }
                        $out[] = $rrow[$key] ?? '';
                    }
                } else {
                    $out = array_values($rrow);
                }
                fputcsv($fh, $out);
            }
            rewind($fh);
            $content = stream_get_contents($fh);
            fclose($fh);

            $resp = new Response($content);
            $resp->headers->set('Content-Type', 'text/csv');
            $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.csv"', (string)$id));
            return $resp;
        }

        // default: generate a very small PDF using FPDF
        try {
            $pdf = new \FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $r->getName() ?: 'Report', 0, 1);
            $pdf->Ln(4);
            $pdf->SetFont('Arial', '', 10);
            if ($headers && $rows) {
                // header
                foreach ($headers as $h) {
                    $pdf->Cell(40, 7, substr($h, 0, 20), 1);
                }
                $pdf->Ln();
                $count = 0;
                foreach ($rows as $row) {
                    foreach ($row as $cell) {
                        $pdf->Cell(40, 6, (string)$cell, 1);
                    }
                    $pdf->Ln();
                    if (++$count > 200) {
                        break;
                    }
                }
            } else {
                $pdf->MultiCell(0, 6, 'No tabular data available for this template yet.');
            }
            $content = $pdf->Output('', 'S');
            $resp = new Response($content);
            $resp->headers->set('Content-Type', 'application/pdf');
            $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.pdf"', (string)$id));
            return $resp;
        } catch (\Throwable $e) {
            // fallback to stub
            $content = "%PDF-1.4\n%\u00e2\u00e3\u00cf\u00d3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
            $resp = new Response($content);
            $resp->headers->set('Content-Type', 'application/pdf');
            $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.pdf"', (string)$id));
            return $resp;
        }
    }

    #[Route('/{id}', name: 'api_reports_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->disableProfiler();
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $repo = $em->getRepository(Report::class);
        $r = $repo->find($id);
        if (!$r) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Ensure the authenticated user owns the report
        $owner = $r->getUser();
        if (!$owner || $owner->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $em->remove($r);
        $em->flush();

        return $this->json(null, 204);
    }
}
