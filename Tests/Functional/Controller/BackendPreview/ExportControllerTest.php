<?php
namespace IchHabRecht\MaskExport\Tests\Functional\Controller\BackendPreview;

/*
 * This file is part of the TYPO3 extension mask_export.
 *
 * (c) 2017 Nicole Cordes <typo3@cordes.co>, CPS-IT GmbH
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

require_once __DIR__ . '/../AbstractExportControllerTestCase.php';

use IchHabRecht\MaskExport\Tests\Functional\Controller\AbstractExportControllerTestCase;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Lang\LanguageService;

class ExportControllerTest extends AbstractExportControllerTestCase
{
    /**
     * @test
     */
    public function checkFluidTemplatePathInBackendPreview()
    {
        $this->assertArrayHasKey('Configuration/PageTSconfig/BackendPreview.typoscript', $this->files);

        // Get templatePaths from file
        $templatePath = [];
        preg_match(
            '#mod\.web_layout\.tt_content\.preview\.([^.]+)\.templateRootPath = [^:]+:[^/]+/(.*)#',
            $this->files['Configuration/PageTSconfig/BackendPreview.typoscript'],
            $templatePath
        );

        $this->assertNotEmpty($templatePath);

        // Fetch supported content types from file
        $matches = [];
        preg_match(
            '#protected \\$supportedContentTypes = ([^;]+);#',
            $this->files['Classes/Hooks/PageLayoutViewDrawItem.php'],
            $matches
        );

        $this->assertCount(2, $matches);

        $supportedContentTypes = eval('return ' . $matches[1] . ';');

        foreach ($supportedContentTypes as $contentType) {
            $this->assertArrayHasKey($templatePath[2] . ucfirst($contentType) . '.html', $this->files);
        }
    }

    /**
     * @test
     */
    public function validateProcessedRowDataFromPageLayoutViewDrawItem()
    {
        $className = 'IchHabRecht\\MaskExampleExport\\Hooks\\PageLayoutViewDrawItem';
        $this->installExtension();

        $this->assertTrue(class_exists($className));

        // Load database fixtures
        $fixturePath = ORIGINAL_ROOT . 'typo3conf/ext/mask_export/Tests/Functional/Fixtures/Database/';
        $this->importDataSet($fixturePath . 'pages.xml');
        $this->importDataSet($fixturePath . 'tt_content.xml');
        $this->importDataSet($fixturePath . 'sys_file.xml');

        // Load backend user and LanguageService for FormEngine
        $this->setUpBackendUserFromFixture(1);
        $languageService = new LanguageService();
        $languageService->init('default');
        $GLOBALS['LANG'] = $languageService;

        // Get StandaloneView mock
        /** @var \PHPUnit_Framework_MockObject_MockObject|StandaloneView $viewMock */
        $viewMock = $this->getMockBuilder(StandaloneView::class)
            ->setMethods(['render'])
            ->getMock();
        $viewMock->expects($this->once())->method('render');

        // Call preProcess function on PageLayoutViewDrawItem
        $pageLayoutView = new PageLayoutView();
        $drawItem = true;
        $headerContent = '';
        $itemContent = '';
        $row = BackendUtility::getRecord('tt_content', 1);
        /** @var \PHPUnit_Framework_MockObject_MockObject|PageLayoutViewDrawItemHookInterface $subject */
        $subject = new $className($viewMock);
        $subject->preProcess($pageLayoutView, $drawItem, $headerContent, $itemContent, $row);

        // Get variable container
        $closure = \Closure::bind(function () use ($viewMock) {
            return $viewMock->baseRenderingContext;
        }, null, StandaloneView::class);
        $renderingContext = $closure();
        if (method_exists($renderingContext, 'getVariableProvider')) {
            $variables = $renderingContext->getVariableProvider();
        } else {
            $variables = $renderingContext->getTemplateVariableContainer();
        }

        $expectedArray = [
            'tx_maskexampleexport_related_content' => [
                0 => [
                    'assets' => [
                        0 => [
                            'uid' => 1,
                            'pid' => 1,
                            'uid_foreign' => 2,
                            'tablenames' => 'tt_content',
                            'fieldname' => 'assets',
                            'table_local' => 'sys_file',
                        ],
                    ],
                ],
            ],
        ];
        $processedRow = $variables->get('processedRow');

        $this->assertArraySubset($expectedArray, $processedRow);

        if (is_array($processedRow['tx_maskexampleexport_related_content'][0]['assets'][0]['uid_local'])) {
            $this->assertArraySubset(
                [
                    0 => [
                        'table' => 'sys_file',
                        'uid' => 1,
                    ],
                ],
                $processedRow['tx_maskexampleexport_related_content'][0]['assets'][0]['uid_local']
            );
        } else {
            $this->assertSame(
                'sys_file_1|ce_nested-content-elements.png',
                $processedRow['tx_maskexampleexport_related_content'][0]['assets'][0]['uid_local']
            );
        }
    }

    /**
     * @test
     */
    public function validateFluidTemplateForSelectboxFields()
    {
        $this->assertArrayHasKey('Resources/Private/Backend/Templates/Content/Simple-element.html', $this->files);
        $this->assertContains(
            '{processedRow.tx_maskexampleexport_simpleselectboxsingle.0} (raw={row.tx_maskexampleexport_simpleselectboxsingle})<br>',
            $this->files['Resources/Private/Backend/Templates/Content/Simple-element.html']
        );
        $this->assertContains(
            '<f:for each="{processedRow.tx_maskexampleexport_simpleselectboxmulti}" as="item">',
            $this->files['Resources/Private/Backend/Templates/Content/Simple-element.html']
        );
    }
}
