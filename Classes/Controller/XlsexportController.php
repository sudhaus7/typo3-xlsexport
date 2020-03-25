<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsexport\Controller;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 *
 *
 * @package xlsexport
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class XlsexportController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
     * @api
     */
    protected $signalSlotDispatcher;
    /**
     * @var array
     */
    protected $cols = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ',
    ];
    /**
     * @var array
     */
    protected $settings = [];
    /**
     * @var array
     */
    private $modTSconfig;
    /**
     * @var string
     */
    private $moduleName = 'tx_xlsexport';

    /**
     * @var object|ConnectionPool
     */
    protected $dbConnection;

    /**
     * XlsexportController constructor.
     */
    public function initializeObject()
    {
        $this->dbConnection = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * action index
     *
     * @return void
     */
    public function indexAction()
    {
        $curr_id = $GLOBALS['_GET']['id'];

        if ($curr_id == 0 || is_null($curr_id)) {
            $this->view->assign('id', $curr_id);
        } else {
            $datasets = [];

            $TSconfig = BackendUtility::getPagesTSconfig($curr_id, 'mod.');
            $this->modTSconfig = $TSconfig['mod.'][$this->moduleName.'.'];

            if (is_array($this->settings) && !empty($this->settings)) {
                $this->settings = array_merge_recursive($this->settings, $this->modTSconfig['settings.']);
            } else {
                $this->settings = $this->modTSconfig['settings.'];
            }

            $hookArray = [];
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])) {
                $hookArray = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
            }

            if (is_array($this->settings['exports.'])) {
                foreach ($this->settings['exports.'] as $key => $config) {
                    $keyWithoutDot = str_replace('.', '', $key);
                    if (strlen($config['check']) > 20) {
                        $table = $config['table'];
                        $checkQuery = $config['check'];
                        if (array_key_exists($table, $hookArray) && is_array($hookArray[$keyWithoutDot])) {
                            foreach ($hookArray[$keyWithoutDot] as $classObj) {
                                $hookObj = GeneralUtility::makeInstance($classObj);
                                if (method_exists($hookObj, 'alternateCheckQuery')) {
                                    $checkQuery = $hookObj->alternateCheckQuery($checkQuery, $this);
                                }
                            }
                        }

                        $statement = sprintf($checkQuery, $curr_id);
                        $dbQuery = $this->dbConnection->getQueryBuilderForTable($table)->getConnection();
                        $result = $dbQuery->fetchAll($statement);

                        // if all datasets from this page should be exported
                        if (sizeof($result) == 1) {
                            $count = $result[0];
                            $datasets[$keyWithoutDot]['count'] = $count['count(uid)'] ?? $count['count(*)'];
                        } else {
                            foreach ($result as $row) {
                                $datasets[$keyWithoutDot]['options'][end($row)]['count'] = $row['count(*)'];
                            }
                        }

                        $datasets[$keyWithoutDot]['label'] = $config['label'] ? $config['label'] : $table;
                        $datasets[$keyWithoutDot]['config'] = $keyWithoutDot;
                    }
                }
            }

            $this->view->assign('id', $curr_id);
            $this->view->assign('settings', $this->settings);
            $this->view->assign('datasets', $datasets);
            $additionalData = [];
            if ($curr_id > 0) {
                if (array_key_exists('additionalData', $hookArray)) {
                    foreach ($hookArray['additionalData'] as $classObj) {
                        $hookObj = GeneralUtility::makeInstance($classObj);
                        if (method_exists($hookObj, 'addAdditionalData')) {
                            $hookObj->addAdditionalData($additionalData, $this);
                        }
                    }
                }
                if (count($additionalData) > 0) {
                    $this->view->assign('additionalData', $additionalData);
                }
            }
        }
    }


    /**
     * action export
     * @param string $config
     * @param string $value
     *
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    public function exportAction($config, $value = null)
    {
        $curr_id = $GLOBALS['_GET']['id'];
        $TSconfig = BackendUtility::getPagesTSconfig($curr_id, 'mod.');
        $this->modTSconfig = $TSconfig['mod.'][$this->moduleName.'.'];

        if (is_array($this->settings) && !empty($this->settings)) {
            $this->settings = array_merge_recursive($this->settings, $this->modTSconfig['settings.']);
        } else {
            $this->settings = $this->modTSconfig['settings.'];
        }
        $settings = $this->settings['exports.'][$config.'.'];

        if (!is_null($value)) {
            $settings['value'] = $value;
        }
        $this->doExport($settings, $curr_id);

        //ins Archiv verschieben
        if ($settings['archive']) {
            $pid_archive = $settings['archive'];

            $dbQuery = $this->dbConnection->getQueryBuilderForTable($settings['table']);
            $dbQuery->update($settings['table'])
                ->where(
                    $dbQuery->expr()->eq('pid', $dbQuery->createNamedParameter($curr_id))
                )
                ->set('pid', $pid_archive)
                ->execute();
        }
    }

    /**
     * @param array $settings
     * @param int $curr_id
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function doExport($settings, $curr_id)
    {
        $hookArray = [];
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])) {
            $hookArray = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
        }

        $spreadSheet = new Spreadsheet();
        $spreadSheet->getProperties()->setCreator("TYPO3 Export")
                                    ->setLastModifiedBy("TYPO3 Export")
                                    ->setTitle("Export "." Dokument")
                                    ->setSubject("Export "." Dokument")
                                    ->setDescription("Export "." Dokument Quelle ");

        $sheet = $spreadSheet->setActiveSheetIndex(0);

        $rowcount = 1;

        $exportfieldnames = [];
        $exportfields = [];

        foreach ($settings['exportfields.'] as $field => $value) {
            $exportfields[] = $value;
        }
        foreach ($settings['exportfieldnames.'] as $names => $value) {
            $exportfieldnames[] = $value;
        }
        $exportQuery = $settings['export'];
        if (array_key_exists($settings['table'], $hookArray) && is_array($hookArray[$settings['table']])) {
            foreach ($hookArray[$settings['table']] as $classObj) {
                $hookObj = GeneralUtility::makeInstance($classObj);
                if (method_exists($hookObj, 'alternateExportQuery')) {
                    $exportQuery = $hookObj->alternateExportQuery($exportQuery, $this, $settings['value']);
                }
            }
        }

        $statement = sprintf($exportQuery, $curr_id);
        $dbQuery = $this->dbConnection->getQueryBuilderForTable($settings['table'])->getConnection();
        $result = $dbQuery->fetchAll($statement);

        $headerManipulated = false;
        if (array_key_exists($settings['table'], $hookArray) && is_array($hookArray[$settings['table']])) {
            foreach ($hookArray[$settings['table']] as $classObj) {
                $hookObj = GeneralUtility::makeInstance($classObj);
                if (method_exists($hookObj, 'alternateHeaderLine')) {
                    $hookObj->alternateHeaderLine($sheet, $this, $exportfieldnames, $rowcount);
                    $headerManipulated = true;
                }
            }
        }
        if (!$headerManipulated) {
            // Zeile mit den Spaltenbezeichungen
            foreach ($exportfieldnames as $field => $value) {
                $sheet->setCellValue($this->cols[$field].$rowcount, $value);
            }
            $rowcount++;
        }

        // Die Datensätze eintragen
        $data = [];
        foreach ($result as $item) {
            $data[] = $item;
        }
        $arguments = [
            [
                'data' => $data,
                'table' => $settings['table']
            ]
        ];

        $arguments = $this->signalSlotDispatcher->dispatch(__CLASS__, 'beforeDataWrite', $arguments);
        $data = $arguments[0]['data'];

        foreach ($data as $curr_number => $curr_data) {
            foreach ($exportfields as $field => $value) {
                $sheet->setCellValue($this->cols[$field].$rowcount, $curr_data[$value]);
            }

            if (array_key_exists($settings['table'], $hookArray) && is_array($hookArray[$settings['table']])) {
                foreach ($hookArray[$settings['table']] as $classObj) {
                    $hookObj = GeneralUtility::makeInstance($classObj);
                    if (method_exists($hookObj, 'addColumns')) {
                        $hookObj->addColumns($sheet, $this, $field, $rowcount);
                    }
                }
            }
            $rowcount++;
        }

        if ($settings['autofilter']) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        }

        for ($i = 0; $i < $field; $i++) {
            $sheet->getColumnDimension($this->cols[$i])->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header(sprintf('Content-Disposition: attachment;filename="%s_%s_%d.xls"', date('Y-m-d-His'), $settings['table'], $curr_id));
        header('Cache-Control: max-age=0');

        $objWriter = IOFactory::createWriter($spreadSheet, 'Xls');
        $objWriter->save('php://output');
        exit;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getCols()
    {
        return $this->cols;
    }
}
