<?php

/**
 * Abbyy FineReader to ALTO Conversion Script
 *
 * @package    AbbyyToAlto
 * @author     Dan Field <dof@llgc.org.uk>
 * @copyright  Copyright (c) 2010 National Library of Wales / Llyfrgell Genedlaethol Cymru. (http://www.llgc.org.uk)
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License 3
 * @version    $Id$
 * @link       http://www.loc.gov/standards/alto/
 * 
 **/
class AbbyyToAlto 
{

    /* 
     * @link http://www.loc.gov/standards/alto/
     */
    const ALTO_NS  = 'http://www.loc.gov/standards/alto/';
    
    /* 
     * @link http://www.abbyy.com/FineReader_xml/FineReader8-schema-v2.xml 
     */
    const ABBYY_NS = 'http://www.abbyy.com/FineReader_xml/FineReader8-schema-v2.xml';
    const ABBYY6_NS = 'http://www.abbyy.com/FineReader_xml/FineReader6-schema-v1.xml';

    protected $_abbyyDom;
    protected $_altoDom;

    protected $_pageCount;
    protected $_textBlockCount;
    protected $_textLineCount;
    protected $_stringCount;
    
    protected $_confidenceTotal;
    protected $_characterCount;

    protected $_altoFilename;
    protected $_abbyyFilename;
    
    protected $_abbyyVersion;
 
    /**
     * AbbyyToAlto Constructor  
     */
    public function __construct() 
    {
        $this->_abbyyDom = new DOMDocument('1.0', 'utf-8');
        $this->_altoDom = new DOMDocument('1.0', 'utf-8');
        $this->_altoDom->preserveWhiteSpace = TRUE; 
        $this->_altoDom->formatOutput = TRUE;
        $this->_pageCount = 0;
        $this->_pageBlockCount = 0;
        $this->_textBlockCount = 0;
        $this->_textLineCount = 0;
        $this->_stringCount = 0;
        $this->_confidenceTotal = 0;
        $this->_characterCount = 0;
        $this->_abbyyVersion = 8;
    }

    /**
     * Convert AbbyyFinereader XML to ALTO XML
     * @param mixed $input Finereader XML Filename 
     */
    public function convert($input) 
    {
        $inputFilename = dirname(__FILE__) . DIRECTORY_SEPARATOR . $input; 
         
        $this->_abbyyDom->load($inputFilename);
        $this->_abbyyFilename = $inputFilename;
        $this->_addAltoHeader();
        $this->_addLayout();
        $this->_addAltoConfidence();
    }

    /**
     * Add ALTO Pages  
     */
    protected function _addPages() 
    {
        $abbyyPages = $this->_abbyyDom->getElementsByTagNameNS(self::ABBYY_NS, 'page');
        if ($abbyyPages->length == 0) {
            $this->_abbyyVersion = 6;
            $abbyyPages = $this->_abbyyDom->getElementsByTagNameNS(self::ABBYY6_NS, 'page');
        }
        foreach ($abbyyPages as $abbyyPage) {
            $this->_pageCount++;
            echo "Page " . $this->_pageCount . " ";
            $height     = $abbyyPage->getAttribute('height');
            $width      = $abbyyPage->getAttribute('width');
            $altoPage = $this->_addPage("Page_{$this->_pageCount}", $height, $width, $this->_pageCount);
            $this->_addPrintSpace($abbyyPage,$altoPage);
            echo "\n";
        }  
    }
    
    /**
     * Add ALTO PrintSpace Element
     * @param DOMElement $abbyyPage AbbyyFinereader page element 
     * @param DOMElement $altoPage ALTO Page element 
     */
    protected function _addPrintSpace($abbyyPage,$altoPage) 
    {
        $xpath = new DOMXpath($this->_abbyyDom);

        $abbyyPageBlocks = $abbyyPage->getElementsByTagName('block');
        if ($abbyyPageBlocks->length > 0)
        {
            $l = null;
            $t = null;
            $r = null;
            $b = null;
            
            for ($i = 0; $i < $abbyyPageBlocks->length; $i++) {
                $abbyyPageBlock = $abbyyPageBlocks->item($i);
                $this->_pageBlockCount++;
    
                if ($abbyyPageBlock->getAttribute('l') < $l || is_null($l) ) {
                    $l = $abbyyPageBlock->getAttribute('l');
                }
                
                if ($abbyyPageBlock->getAttribute('t') < $t || is_null($t)) {
                    $t = $abbyyPageBlock->getAttribute('t');
                }
                
                if ($abbyyPageBlock->getAttribute('r') > $r || is_null($r)) {
                    $r = $abbyyPageBlock->getAttribute('r');
                }
                if ($abbyyPageBlock->getAttribute('b') > $b || is_null($b)) {
                    $b = $abbyyPageBlock->getAttribute('b'); 
                }
            }
            $hpos = $l;
            $vpos = $t;
            $height = $b - $t;
            $width  = $r - $l;
            
    
            $printSpace = $this->_altoDom->createElementNS(self::ALTO_NS, 'PrintSpace');
            $altoPage->appendChild($printSpace);
            $printSpace->setAttributeNS(self::ALTO_NS, 'alto:ID', "PrintSpace_1");
            $printSpace->setAttributeNS(self::ALTO_NS, 'alto:HPOS', $hpos);
            $printSpace->setAttributeNS(self::ALTO_NS, 'alto:VPOS', $vpos);
            $printSpace->setAttributeNS(self::ALTO_NS, 'alto:HEIGHT', $height);
            $printSpace->setAttributeNS(self::ALTO_NS, 'alto:WIDTH', $width);
            
            $this->_addTextBlocks($printSpace,$abbyyPageBlock); 
        }
    }

    /**
     * Add ALTO TextBlock Elements
     * @param DOMElement $printSpace ALTO PrintSpace element 
     * @param DOMElement $abbyyPageBLock AbbyyFinereader block element 
     */
    protected function _addTextBlocks($printSpace,$abbyyPageBlock)
    {
        $abbyyPars = $abbyyPageBlock->getElementsByTagName('par');
        for ($i = 0; $i < $abbyyPars->length; $i++) {
            $abbyyPar = $abbyyPars->item($i);
            $this->_textBlockCount++;
            //$itemCount = $abbyyPar->getElementsByTagName('charParams')->length - 1;
            
            $l = null;
            $t = null;
            $r = null;
            $b = null;
            
            for ($c = 0; $c < $abbyyPar->getElementsByTagName('charParams')->length; $c++) {
                if ($abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('l') < $l || is_null($l) ) {
                    $l = $abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('l');
                }
                
                if ($abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('t') < $t || is_null($t)) {
                    $t = $abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('t');
                }
                
                if ($abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('r') > $r || is_null($r)) {
                    $r = $abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('r');
                }
                if ($abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('b') > $b || is_null($b)) {
                    $b = $abbyyPar->getElementsByTagName('charParams')->item($c)->getAttribute('b'); 
                }
            }
            $hpos = $l;
            $vpos = $t;
            $height = $b - $t;
            $width  = $r - $l;
            
            $altoTextBlock = $this->_altoDom->createElementNS(self::ALTO_NS, 'TextBlock');
            $printSpace->appendChild($altoTextBlock);
            $altoTextBlock->setAttributeNS(self::ALTO_NS, 'alto:ID', "TextBlock_{$this->_textBlockCount}");
            $altoTextBlock->setAttributeNS(self::ALTO_NS, 'alto:HPOS', $hpos);
            $altoTextBlock->setAttributeNS(self::ALTO_NS, 'alto:VPOS', $vpos);
            $altoTextBlock->setAttributeNS(self::ALTO_NS, 'alto:HEIGHT', $height);
            $altoTextBlock->setAttributeNS(self::ALTO_NS, 'alto:WIDTH', $width);
            
            $this->_addTextLines($altoTextBlock, $abbyyPar);
            echo '.';
       }
    }

    /**
     * Add ALTO TextLine Elements
     * @param DOMElement $altoTextBLock ALTO TextBlock element 
     * @param DOMElement $abbyyPar AbbyyFinereader par element 
     */
    protected function _addTextLines($altoTextBlock, $abbyyPar)
    {
        $abbyyLines = $abbyyPar->getElementsByTagName('line');
        foreach ($abbyyLines as $abbyyLine) {
            $this->_textLineCount++;
            $itemCount = $abbyyLine->getElementsByTagName('charParams')->length - 1;
            $l = $abbyyLine->getElementsByTagName('charParams')->item(0)->getAttribute('l');
            $t = $abbyyLine->getElementsByTagName('charParams')->item(0)->getAttribute('t');
            $r = $abbyyLine->getElementsByTagName('charParams')->item($itemCount)->getAttribute('r');
            $b = $abbyyLine->getElementsByTagName('charParams')->item($itemCount)->getAttribute('b');
            
            $hpos = $l;
            $vpos = $t;
            $height = $b - $t;
            $width  = $r - $l;
        
            $altoTextLine = $this->_altoDom->createElementNS(self::ALTO_NS, 'TextLine');
            $altoTextBlock->appendChild($altoTextLine);
            $altoTextLine->setAttributeNS(self::ALTO_NS, 'alto:ID', "TextLine_{$this->_textLineCount}");
            $altoTextLine->setAttributeNS(self::ALTO_NS, 'alto:HPOS', $hpos);
            $altoTextLine->setAttributeNS(self::ALTO_NS, 'alto:VPOS', $vpos);
            $altoTextLine->setAttributeNS(self::ALTO_NS, 'alto:HEIGHT', $height);
            $altoTextLine->setAttributeNS(self::ALTO_NS, 'alto:WIDTH', $width);
            
            $this->_addStrings($altoTextLine, $abbyyLine);
        }
    }

    /**
     * Add ALTO String Elements
     * @param DOMElement $altoTextLine ALTO TextLine element 
     * @param DOMElement $abbyyLine AbbyyFinereader line element 
     */
    protected function _addStrings($altoTextLine, $abbyyLine) 
    {
        $abbyyCharParams = $abbyyLine->getElementsByTagName('charParams');
        $charCount = $abbyyCharParams->length;
        $string = '';
        $l = null;
        $t = null;
        $r = null;
        $b = null;
        
        for ($c = 0; $c < $charCount; $c++) {
            if ($abbyyCharParams->item($c) instanceof DOMElement) {
                if ($string == '') {
                    // if first character of a new string, set hpos and vpos
                    $l = $abbyyCharParams->item($c)->getAttribute('l');
                    $t = $abbyyCharParams->item($c)->getAttribute('t');
                }
                $string .= $abbyyCharParams->item($c)->nodeValue;
                $this->_characterCount++;
                $this->_confidenceTotal += $abbyyCharParams->item($c)->getAttribute('charConfidence');
            }
            if (!($abbyyCharParams->item($c+1) instanceof DOMElement) || $abbyyCharParams->item($c+1)->nodeValue == ' ' || $abbyyCharParams->item($c+1)->nodeValue == '') {
                //if ($abbyyCharParams->item($c+1)->nodeValue == ' ') {
                    $this->_stringCount++;
                    // if last character of a string, set height and width
                    $r = $abbyyCharParams->item($c)->getAttribute('r');
                    $b = $abbyyCharParams->item($c)->getAttribute('b');
                    
                    $hpos = $l;
                    $vpos = $t;
                    $height = $b - $t;
                    $width  = $r - $l;
                    
                    $altoString = $this->_altoDom->createElementNS(self::ALTO_NS, 'String');
                    $altoTextLine->appendChild($altoString);
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:ID', "String_{$this->_stringCount}");
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:CONTENT', $string);
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:HPOS', $hpos);
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:VPOS', $vpos);
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:HEIGHT', $height);
                    $altoString->setAttributeNS(self::ALTO_NS, 'alto:WIDTH', $width);
                    
                    $l = null;
                    $t = null;
                    $r = null;
                    $b = null;
                    
                    $string = '';
                    $c++;
                //}
            }
        }
        
    }

    /**
     * Add ALTO Document Header
     */
    protected function _addAltoHeader() 
    {
        $alto = $this->_altoDom->createElementNS(self::ALTO_NS, 'alto');
        $this->_altoDom->appendChild($alto);
        $alto->setAttributeNS(self::ALTO_NS, 
                            'xsi', 
                            'http://www.w3.org/2001/XMLSchema-instance');
        $alto->setAttributeNS(self::ALTO_NS, 
                            'alto', 
                            'http://www.loc.gov/standards/alto http://www.loc.gov/standards/alto/alto-v2.0.xsd');
        
        
        $description = $this->_altoDom->createElementNS(self::ALTO_NS, 'Description');
        $alto->appendChild($description);
        
        $measurementUnit = $this->_altoDom->createElementNS(self::ALTO_NS, 'MeasurementUnit');
        $description->appendChild($measurementUnit);
        
        $sourceImageInformation = $this->_altoDom->createElementNS(self::ALTO_NS, 'sourceImageInformation');
        $description->appendChild($sourceImageInformation);
    
        $filename = basename($this->_abbyyFilename);
        $fileName = $this->_altoDom->createElementNS(self::ALTO_NS, 'fileName', $filename);
        $sourceImageInformation->appendChild($fileName);
        
        $ocrProcessing = $this->_altoDom->createElementNS(self::ALTO_NS, 'OCRProcessing');
        $description->appendChild($ocrProcessing);
        $ocrProcessing->setAttributeNS(self::ALTO_NS, 'alto:ID', 'OCR_1');
        
        $ocrProcessingStep = $this->_altoDom->createElementNS(self::ALTO_NS, 'ocrProcessingStep');
        $ocrProcessing->appendChild($ocrProcessingStep);
        
        $date = date('Y-m-d',filectime($this->_abbyyFilename));
        $processingDateTime = $this->_altoDom->createElementNS(self::ALTO_NS, 'processingDateTime', $date);
        $ocrProcessingStep->appendChild($processingDateTime);
    }
    
    /**
     * Add ALTO Character Confidence
     */
    protected function _addAltoConfidence() 
    {
        $ocrProcessingStep = $this->_altoDom->getElementsByTagName('ocrProcessingStep');
        if ($this->_characterCount != 0) {
            $confidence = round($this->_confidenceTotal / $this->_characterCount, 2);
        } else {
            $confidence = 0;
        }
        $settings = "OCR Average Character Confidence {$confidence}%";
        $processingStepSettings = $this->_altoDom->createElementNS(self::ALTO_NS, 'processingStepSettings', $settings);
        $ocrProcessingStep->item(0)->appendChild($processingStepSettings);
    }
    
    /**
     * Add ALTO Layout Element
     */
    protected function _addLayout() 
    {
        $layout = $this->_altoDom->createElementNS(self::ALTO_NS, 'Layout');
        $this->_altoDom->documentElement->appendChild($layout);
        $this->_addPages();
    }

    /**
     * Add ALTO Page Elements
     * @param mixed $id Page ID 
     * @param mixed $height Page Height in pixels 
     * @param mixed $width Page Width in pixels 
     * @param mixed $physicalImageNr Page Physical Image Number
     * @param mixed $quality Page Quality Information 
     */    
    protected function _addPage($id, $height, $width, $physicalImageNr) 
    {    
        $page = $this->_altoDom->createElementNS(self::ALTO_NS, 'Page');
        $this->_altoDom->documentElement->appendChild($page);
        $page->setAttributeNS(self::ALTO_NS, 'alto:ID', $id);
        $page->setAttributeNS(self::ALTO_NS, 'alto:HEIGHT', $height);
        $page->setAttributeNS(self::ALTO_NS, 'alto:WIDTH', $width);
        $page->setAttributeNS(self::ALTO_NS, 'alto:PHYSICAL_IMAGE_NR', $physicalImageNr);
        return $page;
    }
    
    /**
     * Return ALTO XML Document as String
     * @return mixed ALTO XML Document
     */
    public function toString() 
    {
        return $this->_altoDom->saveXml();
    }
  
    /**
     * Save ALTO XML Document to filesystem
     * @param mixed $filename ALTO Output Document filename
     * @return bool success passed from file_put_contents 
     */
    public function toFile($filename) 
    {
        $this->_altoFilename = dirname(__FILE__) . DIRECTORY_SEPARATOR . $filename;
        return file_put_contents($this->_altoFilename,$this->_altoDom->saveXml());
    }
}
 
$abbyyToAlto = new AbbyyToAlto;
$abbyyToAlto->convert($argv[1]);
$abbyyToAlto->toFile($argv[2]);