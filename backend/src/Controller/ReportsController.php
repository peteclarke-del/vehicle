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
use App\Entity\UserPreference;
use App\Service\ReportEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

#[Route('/api/reports')]

/**
 * class ReportsController
 */
class ReportsController extends AbstractController
{
    private const BATCH_SIZE = 100;

    /**
     * @var ReportEngine
     */
    private ReportEngine $reportEngine;

    /**
     * function __construct
     *
     * @param ReportEngine $reportEngine
     *
     * @return void
     */
    public function __construct(ReportEngine $reportEngine)
    {
        $this->reportEngine = $reportEngine;
    }

    /**
     * function disableProfiler
     *
     * @return void
     */
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

    /**
     * function optimizeForFileGeneration
     *
     * Optimize memory for heavy operations like file generation.
     *
     * @return void
     */
    private function optimizeForFileGeneration(): void
    {
        $this->disableProfiler();

        // Increase execution time for large reports
        @set_time_limit(120);

        // Disable output buffering to reduce memory usage
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Trigger garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    #[Route('', name: 'api_reports_list', methods: ['GET'])]

    /**
     * function list
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     *
     * @return JsonResponse
     */
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
            $payload = $r->getPayload() ?? [];
            $out[] = [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'template' => $r->getTemplateKey(),
                'type' => $payload['type'] ?? $payload['name'] ?? $r->getName(),
                'periodLabel' => $payload['periodLabel'] ?? ($payload['period']['label'] ?? null),
                'periodMonths' => $payload['periodMonths'] ?? ($payload['period']['months'] ?? null),
                'vehicleId' => $r->getVehicleId(),
                'generatedAt' => $r->getGeneratedAt()->format(DATE_ATOM),
            ];
        }

        return $this->json($out);
    }

    #[Route('', name: 'api_reports_create', methods: ['POST'])]

    /**
     * function create
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     *
     * @return JsonResponse
     */
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

    /**
     * function download
     *
     * @param mixed $id
     * @param Request $request
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    public function download(int|string $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->optimizeForFileGeneration();
        
        $repo = $em->getRepository(Report::class);
        $r = $repo->find((int)$id);
        if (!$r) {
            return new Response('Not found', 404);
        }

        $payload = $r->getPayload() ?? [];
        $template = $payload['templateContent'] ?? null;

        // Enforce: backend must not read frontend source files for templates.
        if (!is_array($template)) {
            return new Response('Persisted report missing templateContent; backend requires templateContent to be present. Recreate the report including the template JSON.', 422);
        }

        // Check for format query parameter (xlsx or pdf)
        $requestedFormat = $request->query->get('format');
        
        // Determine file type: use query param if provided, otherwise fall back to template default
        $fileType = 'xlsx'; // Default to xlsx for better compatibility
        if ($requestedFormat && in_array($requestedFormat, ['xlsx', 'pdf', 'csv'])) {
            $fileType = $requestedFormat;
        } elseif (is_array($template) && isset($template['outputs']) && is_array($template['outputs'])) {
            foreach ($template['outputs'] as $o) {
                if (isset($o['mode']) && $o['mode'] === 'file' && !empty($o['fileType'])) {
                    $fileType = $o['fileType'];
                    break;
                }
            }
        }

        // Extract report parameters
        $reportParams = [];
        $vehicleId = $r->getVehicleId();
        if ($vehicleId) {
            $reportParams['vehicle_id'] = $vehicleId;
        }
        $periodFrom = $payload['period']['from'] ?? $payload['from'] ?? null;
        $periodTo = $payload['period']['to'] ?? $payload['to'] ?? null;
        if ($periodFrom) {
            $reportParams['from'] = $periodFrom;
        }
        if ($periodTo) {
            $reportParams['to'] = $periodTo;
        }

        // Fetch user's distance unit preference (default to 'miles' if not set)
        $user = $this->getUser();
        $distanceUnit = 'miles'; // Default
        if ($user) {
            $prefRepo = $em->getRepository(UserPreference::class);
            $pref = $prefRepo->findOneBy(['user' => $user, 'name' => 'distanceUnit']);
            if ($pref && $pref->getValue()) {
                $val = $pref->getValue();
                // Handle JSON-encoded values
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
                    $distanceUnit = $decoded;
                } else {
                    $distanceUnit = $val;
                }
            }
        }
        $reportParams['distanceUnit'] = $distanceUnit;

        // Clear EntityManager to free memory before heavy processing
        $em->clear();

        // Check if template uses the new ReportEngine format (has 'layout' or 'dataSources')
        $useReportEngine = isset($template['layout']) || isset($template['dataSources']);

        if ($useReportEngine) {
            return $this->generateReportWithEngine($r, $template, $reportParams, $fileType);
        }

        if ($fileType === 'xlsx' && is_array($template)) {
            return $this->generateXlsxReport($r, $template, $reportParams, $em);
        }

        if ($fileType === 'csv') {
            return $this->generateCsvReport($r, $template, $reportParams, $em);
        }

        // default: generate PDF
        return $this->generatePdfReport($r, $template, $reportParams, $em);
    }

    /**
     * function generateReportWithEngine
     *
     * Generate report using the new ReportEngine (template-driven).
     *
     * @param Report $r
     * @param array $template
     * @param array $reportParams
     * @param string $format
     *
     * @return Response
     */
    private function generateReportWithEngine(Report $r, array $template, array $reportParams, string $format): Response
    {
        try {
            $result = $this->reportEngine->generate($template, $reportParams, $format);

            $response = new Response($result['content']);
            $response->headers->set('Content-Type', $result['mimeType']);
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result['filename']));
            $response->headers->set('Content-Length', (string)strlen($result['content']));

            return $response;
        } catch (\Throwable $e) {
            return new Response('Error generating report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * function generateXlsxReport
     *
     * Generate XLSX report with memory-efficient streaming.
     *
     * @param Report $r
     * @param array $template
     * @param array $reportParams
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    private function generateXlsxReport(Report $r, array $template, array $reportParams, EntityManagerInterface $em): Response
    {
        // Normalize sheets: prefer explicit 'sheets', else fall back to first table element
        $sheets = $template['sheets'] ?? null;
        if (!$sheets && !empty($template['elements'])) {
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

        if (empty($sheets) || !is_array($sheets)) {
            return new Response('No sheet definitions found in template', 422);
        }

        // Use a temporary file instead of memory to reduce memory footprint
        $tmpFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';

        try {
            $spreadsheet = new Spreadsheet();
            // Disable caching to cells collection to reduce memory
            $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
            
            $sheetIndex = 0;

            foreach ($sheets as $si => $sdef) {
                $sheet = ($sheetIndex === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
                $title = $sdef['name'] ?? ($template['name'] ?? ('Sheet' . ($si + 1)));
                $sheet->setTitle(substr((string)$title, 0, 31));

                $cols = $sdef['columns'] ?? [];
                
                // Fetch rows using memory-efficient method (array hydration)
                $rows = $this->fetchRowsForSheet($sdef, $reportParams, $em);

                // If columns not provided but rows exist, infer from first row
                if (empty($cols) && !empty($rows)) {
                    $first = $rows[0] ?? [];
                    $cols = [];
                    foreach (array_keys($first) as $k) {
                        $cols[] = ['key' => $k, 'label' => ucfirst(str_replace('_', ' ', $k))];
                    }
                }

                // Process derived fields (e.g., mpg) - only if we have derived columns
                $derivedDefs = array_filter($cols, fn($c) => !empty($c['derived']));
                if (!empty($derivedDefs) && !empty($rows)) {
                    $rows = $this->computeDerivedFields($rows, $derivedDefs);
                }

                // Apply sorting
                $rows = $this->applySorting($rows, $cols, $sdef);

                // Compute aggregates if needed
                $aggregates = [];
                foreach ($cols as $ci => $c) {
                    if (!empty($c['aggregate'])) {
                        $aggregates[$ci] = $c['aggregate'];
                    }
                }

                // Write headers
                $colNum = 1;
                foreach ($cols as $c) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum) . '1', $c['label'] ?? ($c['key'] ?? ''));
                    if (!empty($c['width'])) {
                        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colNum))->setWidth((float)$c['width']);
                    }
                    $colNum++;
                }

                // Header style
                if (count($cols) > 0) {
                    $lastCol = Coordinate::stringFromColumnIndex(count($cols));
                    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDEDED']],
                    ]);
                }

                // Write rows in batches to manage memory
                $rnum = 2;
                foreach ($rows as $row) {
                    $colNum = 1;
                    foreach ($cols as $c) {
                        $colLetter = Coordinate::stringFromColumnIndex($colNum);
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
                            $sheet->getStyle($coord)->getAlignment()->setHorizontal(
                                $align === 'right' ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT : 
                                ($align === 'center' ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER : 
                                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                            );
                        }
                        $colNum++;
                    }
                    $rnum++;
                }

                // Write aggregate row if requested
                if (!empty($aggregates) && !empty($rows)) {
                    $this->writeAggregateRow($sheet, $rows, $cols, $aggregates, $rnum);
                }

                // Free the rows array to reclaim memory
                unset($rows);
                
                $sheetIndex++;
            }

            // Write to temp file instead of memory
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false); // Skip formula calculation to save memory
            $writer->save($tmpFile);

            // Free spreadsheet memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            gc_collect_cycles();

            // Stream the file back to the client
            $response = new StreamedResponse(function () use ($tmpFile) {
                $handle = fopen($tmpFile, 'rb');
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
                @unlink($tmpFile);
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.xlsx"', (string)$r->getId()));
            $response->headers->set('Content-Length', (string)filesize($tmpFile));

            return $response;

        } catch (\Throwable $e) {
            @unlink($tmpFile);
            return new Response('Error generating XLSX: ' . $e->getMessage(), 500);
        }
    }

    /**
     * function fetchRowsForSheet
     *
     * Fetch rows for a sheet using memory-efficient array hydration.
     *
     * @param array $sdef
     * @param array $reportParams
     * @param EntityManagerInterface $em
     *
     * @return array
     */
    private function fetchRowsForSheet(array $sdef, array $reportParams, EntityManagerInterface $em): array
    {
        $rows = [];
        
        // Direct data provided
        if (!empty($sdef['data']) && is_array($sdef['data'])) {
            return $sdef['data'];
        }
        
        // Raw SQL query
        if (!empty($sdef['query'])) {
            $conn = $em->getConnection();
            $params = array_merge($reportParams, $sdef['params'] ?? []);
            $stmt = $conn->executeQuery($sdef['query'], $params);
            return $stmt->fetchAllAssociative();
        }
        
        // Entity source - use array hydration for memory efficiency
        if (!empty($sdef['source'])) {
            return $this->fetchEntityRows($sdef['source'], $reportParams, $em);
        }
        
        return $rows;
    }

    /**
     * function fetchEntityRows
     *
     * Fetch entity rows using array hydration to avoid Doctrine object overhead.
     *
     * @param string $source
     * @param array $reportParams
     * @param EntityManagerInterface $em
     *
     * @return array
     */
    private function fetchEntityRows(string $source, array $reportParams, EntityManagerInterface $em): array
    {
        $rows = [];
        $vehicleId = $reportParams['vehicle_id'] ?? null;

        switch ($source) {
            case 'fuelRecords':
                $qb = $em->createQueryBuilder()
                    ->select('fr.id, fr.date, fr.mileage, fr.litres, fr.cost, IDENTITY(fr.vehicle) as vehicle_id')
                    ->from(FuelRecord::class, 'fr');
                if ($vehicleId) {
                    $qb->where('fr.vehicle = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'id' => $row['id'],
                        'date' => $row['date'] instanceof \DateTimeInterface ? $row['date']->format('Y-m-d') : ($row['date'] ?? ''),
                        'mileage' => $row['mileage'],
                        'litres' => $row['litres'],
                        'cost' => $row['cost'],
                    ];
                }
                break;

            case 'parts':
                $qb = $em->createQueryBuilder()
                    ->select('p.id, p.purchaseDate, p.description, p.cost, IDENTITY(p.vehicle) as vehicle_id')
                    ->from(Part::class, 'p');
                if ($vehicleId) {
                    $qb->where('p.vehicle = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'date' => $row['purchaseDate'] instanceof \DateTimeInterface ? $row['purchaseDate']->format('Y-m-d') : ($row['purchaseDate'] ?? ''),
                        'item' => $row['description'] ?? '',
                        'cost' => $row['cost'],
                    ];
                }
                break;

            case 'consumables':
                $qb = $em->createQueryBuilder()
                    ->select('c.id, c.lastChanged, c.description, c.cost, IDENTITY(c.vehicle) as vehicle_id')
                    ->from(Consumable::class, 'c');
                if ($vehicleId) {
                    $qb->where('c.vehicle = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'date' => $row['lastChanged'] instanceof \DateTimeInterface ? $row['lastChanged']->format('Y-m-d') : ($row['lastChanged'] ?? ''),
                        'item' => $row['description'] ?? '',
                        'cost' => $row['cost'],
                    ];
                }
                break;

            case 'serviceRecords':
                $qb = $em->createQueryBuilder()
                    ->select('sr.id, sr.serviceDate, sr.serviceType, sr.laborCost, IDENTITY(sr.vehicle) as vehicle_id')
                    ->from(ServiceRecord::class, 'sr');
                if ($vehicleId) {
                    $qb->where('sr.vehicle = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'date' => $row['serviceDate'] instanceof \DateTimeInterface ? $row['serviceDate']->format('Y-m-d') : ($row['serviceDate'] ?? ''),
                        'item' => $row['serviceType'] ?? '',
                        'cost' => $row['laborCost'],
                    ];
                }
                break;

            case 'mot':
            case 'motRecords':
                $qb = $em->createQueryBuilder()
                    ->select('m.id, m.testDate, m.result, m.totalCost, IDENTITY(m.vehicle) as vehicle_id')
                    ->from(MotRecord::class, 'm');
                if ($vehicleId) {
                    $qb->where('m.vehicle = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'date' => $row['testDate'] instanceof \DateTimeInterface ? $row['testDate']->format('Y-m-d') : ($row['testDate'] ?? ''),
                        'item' => 'MOT ' . ($row['result'] ?? ''),
                        'cost' => $row['totalCost'],
                    ];
                }
                break;

            case 'vehicles':
                $qb = $em->createQueryBuilder()
                    ->select('v.id, v.registrationNumber, v.make, v.model')
                    ->from(Vehicle::class, 'v');
                if ($vehicleId) {
                    $qb->where('v.id = :vehicleId')->setParameter('vehicleId', $vehicleId);
                }
                $qb->setMaxResults(5000);
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $rows[] = [
                        'id' => $row['id'],
                        'registration' => $row['registrationNumber'] ?? '',
                        'make' => $row['make'] ?? '',
                        'model' => $row['model'] ?? '',
                    ];
                }
                break;
        }

        // Clear EntityManager to free memory after fetching
        $em->clear();
        
        return $rows;
    }

    /**
     * function computeDerivedFields
     *
     * Compute derived fields like MPG.
     *
     * @param array $rows
     * @param array $derivedDefs
     *
     * @return array
     */
    private function computeDerivedFields(array $rows, array $derivedDefs): array
    {
        // Sort by date for MPG calculation
        usort($rows, fn($a, $b) => (strtotime($a['date'] ?? '') ?: 0) <=> (strtotime($b['date'] ?? '') ?: 0));
        
        $prev = null;
        foreach ($rows as $i => $row) {
            foreach ($derivedDefs as $dcol) {
                if (($dcol['derived'] ?? '') === 'mpg') {
                    $mpgKey = $dcol['key'] ?? $dcol['field'] ?? 'mpg';
                    $mileage = isset($row['mileage']) ? (float)$row['mileage'] : null;
                    $litres = isset($row['litres']) ? (float)$row['litres'] : null;
                    $mpgVal = '';
                    
                    if ($prev && is_numeric($mileage) && is_numeric($prev['mileage']) && is_numeric($litres) && $litres > 0) {
                        $delta = $mileage - $prev['mileage'];
                        if ($delta > 0) {
                            // Convert to MPG: miles / (litres * 0.219969)
                            $gallons = $litres * 0.219969;
                            $mpgVal = round($delta / $gallons, 2);
                        }
                    }
                    $rows[$i][$mpgKey] = $mpgVal !== '' ? number_format($mpgVal, 2) : '';
                }
            }
            $prev = $row;
        }
        
        return $rows;
    }

    /**
     * function applySorting
     *
     * Apply sorting to rows.
     *
     * @param array $rows
     * @param array $cols
     * @param array $sdef
     *
     * @return array
     */
    private function applySorting(array $rows, array $cols, array $sdef): array
    {
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
                        if ($av < $bv) return -1 * $dir;
                        if ($av > $bv) return 1 * $dir;
                    } else {
                        $cmp = strcmp((string)$av, (string)$bv);
                        if ($cmp !== 0) return $cmp * $dir;
                    }
                }
                return 0;
            });
        }
        
        return $rows;
    }

    /**
     * function writeAggregateRow
     *
     * Write aggregate row to sheet.
     *
     * @param mixed $sheet
     * @param array $rows
     * @param array $cols
     * @param array $aggregates
     * @param int $rnum
     *
     * @return void
     */
    private function writeAggregateRow($sheet, array $rows, array $cols, array $aggregates, int $rnum): void
    {
        $sheet->setCellValue('A' . $rnum, 'Totals');
        
        foreach ($cols as $ci => $c) {
            if (isset($aggregates[$ci])) {
                $key = $c['key'] ?? $c['field'] ?? null;
                if ($key) {
                    $type = $aggregates[$ci];
                    $vals = array_column($rows, $key);
                    $numeric = array_filter($vals, 'is_numeric');
                    
                    $agg = match($type) {
                        'sum' => array_sum($numeric),
                        'avg' => count($numeric) ? array_sum($numeric) / count($numeric) : 0,
                        'count' => count($vals),
                        default => '',
                    };
                    
                    $colLetter = Coordinate::stringFromColumnIndex($ci + 1);
                    $sheet->setCellValue($colLetter . $rnum, $agg);
                }
            }
        }
    }

    /**
     * function generateCsvReport
     *
     * Generate CSV report.
     *
     * @param Report $r
     * @param array $template
     * @param array $reportParams
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    private function generateCsvReport(Report $r, array $template, array $reportParams, EntityManagerInterface $em): Response
    {
        $rows = [];
        $headers = [];
        
        // Extract first table from template
        if (isset($template['elements']) && is_array($template['elements'])) {
            foreach ($template['elements'] as $el) {
                if (($el['type'] ?? '') === 'table') {
                    $cols = $el['columns'] ?? [];
                    $headers = array_map(fn($c) => $c['label'] ?? ($c['key'] ?? ''), $cols);
                    $source = $el['source'] ?? '';
                    if ($source) {
                        $rows = $this->fetchEntityRows($source, $reportParams, $em);
                    }
                    break;
                }
            }
        }

        $fh = fopen('php://temp', 'r+');
        if ($headers) {
            fputcsv($fh, $headers);
        }
        foreach ($rows as $row) {
            $out = [];
            if ($headers) {
                foreach ($headers as $h) {
                    $k = strtolower(str_replace(' ', '_', $h));
                    $out[] = $row[$k] ?? ($row[array_key_first($row)] ?? '');
                }
            } else {
                $out = array_values($row);
            }
            fputcsv($fh, $out);
        }
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        $resp = new Response($content);
        $resp->headers->set('Content-Type', 'text/csv');
        $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.csv"', (string)$r->getId()));
        return $resp;
    }

    /**
     * function generatePdfReport
     *
     * Generate PDF report.
     *
     * @param Report $r
     * @param array $template
     * @param array $reportParams
     * @param EntityManagerInterface $em
     *
     * @return Response
     */
    private function generatePdfReport(Report $r, array $template, array $reportParams, EntityManagerInterface $em): Response
    {
        $rows = [];
        $headers = [];
        
        // Extract first table from template
        if (isset($template['elements']) && is_array($template['elements'])) {
            foreach ($template['elements'] as $el) {
                if (($el['type'] ?? '') === 'table') {
                    $cols = $el['columns'] ?? [];
                    $headers = array_map(fn($c) => $c['label'] ?? ($c['key'] ?? ''), $cols);
                    $source = $el['source'] ?? '';
                    if ($source) {
                        $rows = $this->fetchEntityRows($source, $reportParams, $em);
                    }
                    break;
                }
            }
        }

        try {
            $pdf = new \FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $r->getName() ?: 'Report', 0, 1);
            $pdf->Ln(4);
            $pdf->SetFont('Arial', '', 10);
            
            if ($headers && $rows) {
                // Header row
                foreach ($headers as $h) {
                    $pdf->Cell(40, 7, substr($h, 0, 20), 1);
                }
                $pdf->Ln();
                
                // Data rows (limit to 200 for PDF)
                $count = 0;
                foreach ($rows as $row) {
                    foreach ($row as $cell) {
                        $pdf->Cell(40, 6, substr((string)$cell, 0, 25), 1);
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
            $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.pdf"', (string)$r->getId()));
            return $resp;
            
        } catch (\Throwable $e) {
            // Fallback to minimal PDF stub
            $content = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
            $resp = new Response($content);
            $resp->headers->set('Content-Type', 'application/pdf');
            $resp->headers->set('Content-Disposition', sprintf('attachment; filename="report_%s.pdf"', (string)$r->getId()));
            return $resp;
        }
    }

    #[Route('/{id}', name: 'api_reports_delete', methods: ['DELETE'])]

    /**
     * function delete
     *
     * @param int $id
     * @param EntityManagerInterface $em
     *
     * @return JsonResponse
     */
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
