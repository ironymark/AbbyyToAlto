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
    
    protected $docName;
    
    protected $_abbyyVersion;
 
    protected $_altoStyle;
    protected $altoStylesMap;// = array();
    /**
     * AbbyyToAlto Constructor  
     */
    public function __construct() 
    {
        $this->_abbyyDom = new DOMDocument('1.0', 'utf-8');
        $this->_pageCount = 0;
        $this->init();
    }
    protected function init(){
        $this->_altoDom = new DOMDocument('1.0', 'utf-8');
        $this->_altoStyle = $this->_altoDom->createElementNS(self::ALTO_NS, 'Styles');
        $this->_altoDom->preserveWhiteSpace = TRUE; 
        $this->_altoDom->formatOutput = TRUE;
        $this->_pageBlockCount = 0;
        $this->_textBlockCount = 0;
        $this->_textLineCount = 0;
        $this->_stringCount = 0;
        $this->_confidenceTotal = 0;
        $this->_characterCount = 0;
        $this->_abbyyVersion = 8;
        $this->altoStylesMap = array();
    }

    /**
     * Convert AbbyyFinereader XML to ALTO XML
     * @param mixed $input Finereader XML Filename 
     */
    public function convert($input,$output) 
    {
        $inputFilename = dirname(__FILE__) . DIRECTORY_SEPARATOR . $input; 
         
        $this->_abbyyDom->load($inputFilename);
        $this->_abbyyFilename = $inputFilename;

        $tmp = explode(".",$output);

                
        $abbyyPages = $this->_abbyyDom->getElementsByTagNameNS(self::ABBYY_NS, 'page');
        if ($abbyyPages->length == 0) {
            $this->_abbyyVersion = 6;
            $abbyyPages = $this->_abbyyDom->getElementsByTagNameNS(self::ABBYY6_NS, 'page');
        }
        
        foreach ($abbyyPages as $abbyyPage) {
            $height         = $abbyyPage->getAttribute('height');
            $width          = $abbyyPage->getAttribute('width');
            $resolution     = $abbyyPage->getAttribute('resolution');
            $this->docName  = $tmp[0];
            $this->docName .= "_Pg_".$this->_pageCount;
            $this->_addAltoHeader($resolution);
            $layout         = $this->_addLayout();
            $altoPage       = $this->_addPage("Page.{$this->_pageCount}", $height, $width, $this->_pageCount, $layout);
            $this->_addPrintSpace($abbyyPage,$altoPage);
            $this->_addAltoConfidence();
            $this->_altoDom->documentElement->appendChild($this->_altoStyle);
            $this->toFile($this->docName.".xml");
            
            echo "Page {$this->_pageCount} converted successfully\n";
            $this->_pageCount++;
            $this->init();
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
            $printSpace->setAttribute( 'ID', "PS.0");
            $printSpace->setAttribute( 'HPOS', $hpos);
            $printSpace->setAttribute( 'VPOS', $vpos);
            $printSpace->setAttribute( 'HEIGHT', $height);
            $printSpace->setAttribute( 'WIDTH', $width);
            
            for ($i = 0; $i < $abbyyPageBlocks->length; $i++) {
                $abbyyPageBlock = $abbyyPageBlocks->item($i);
                if($abbyyPageBlock->getAttribute('blockType') != "Text")
                    continue;
                $this->_addTextBlocks($printSpace,$abbyyPageBlock);
            }
            
            if($this->_textBlockCount==0) {
                $dummy = $this->_altoDom->createElementNS(self::ALTO_NS, 'Empty');
                $printSpace->appendChild($dummy);
            }
        }
    }

    /**
     * Add ALTO TextBlock Elements
     * @param DOMElement $printSpace ALTO PrintSpace element 
     * @param DOMElement $abbyyPageBLock AbbyyFinereader block element 
     */
    protected function _addTextBlocks($printSpace,$abbyyPageBlock) {
        $l = $abbyyPageBlock->getAttribute('l');
        $t = $abbyyPageBlock->getAttribute('t');
        $r = $abbyyPageBlock->getAttribute('r');
        $b = $abbyyPageBlock->getAttribute('b');
        $hpos = $l;
        $vpos = $t;
        $height = $b - $t;
        $width  = $r - $l;
        
        $this->_textBlockCount++;
        $this->_stringCount = 0;
        $this->_textLineCount = 0;
        
        $altoTextBlock = $this->_altoDom->createElementNS(self::ALTO_NS, 'TextBlock');
        $printSpace->appendChild($altoTextBlock);
        $altoTextBlock->setAttribute('ID', "TB.{$this->docName}.{$this->_textBlockCount}");
        $altoTextBlock->setAttribute('HPOS', $hpos);
        $altoTextBlock->setAttribute('VPOS', $vpos);
        $altoTextBlock->setAttribute('HEIGHT', $height);
        $altoTextBlock->setAttribute('WIDTH', $width);

        $abbyyTexts = $abbyyPageBlock->getElementsByTagName('text');
        for ($i = 0; $i < $abbyyTexts->length; $i++) {
            $abbyyText = $abbyyTexts->item($i);
            $this->_addParagraph($altoTextBlock,$abbyyText);
        } 
    }

    /**
     * Add ALTO Paragraph Elements
     * @param DOMElement $altoTextBLock ALTO TextBlock element 
     * @param DOMElement $abbyyTexts AbbyyFinereader text element 
     */
    protected function _addParagraph($altoTextBlock,$abbyyText)
    {
        $abbyyPars = $abbyyText->getElementsByTagName('par');
        for ($i = 0; $i < $abbyyPars->length; $i++) {
            $abbyyPar = $abbyyPars->item($i);
            $this->_addTextLines($altoTextBlock, $abbyyPar);
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
            $altoTextLine->setAttribute('ID', "TB.{$this->docName}.{$this->_textBlockCount}_{$this->_textLineCount}");
            $altoTextLine->setAttribute('HPOS', $hpos);
            $altoTextLine->setAttribute('VPOS', $vpos);
            $altoTextLine->setAttribute('HEIGHT', $height);
            $altoTextLine->setAttribute('WIDTH', $width);
            $this->_addStyles($altoTextLine, $abbyyLine);
            $this->_textLineCount++;
        }
    }

    /**
     * Add ALTO Styles Element
     * @param DOMElement $altoTextLine ALTO TextLine element 
     * @param DOMElement $abbyyLine AbbyyFinereader line element 
     */
 	protected function _addStyles($altoTextLine, $abbyyLine){
 		$abbyyFormattings = $abbyyLine->getElementsByTagName('formatting');
 		//echo $abbyyFormattings->length."\n";
        foreach ($abbyyFormattings as $abbyyFormatting) {
        	 $lang = $abbyyFormatting->getAttribute("lang");
        	 $ff = $abbyyFormatting->getAttribute("ff");//font family
        	 $fs = $abbyyFormatting->getAttribute("fs");
        	 $underline = $abbyyFormatting->getAttribute("underline");
        	 $bold = $abbyyFormatting->getAttribute("bold");
        	 $italic = $abbyyFormatting->getAttribute("italic");
        	 $smallcaps = $abbyyFormatting->getAttribute("smallcaps");
        	 
        	 //echo "$lang, $ff, $fs, $underline, $bold, $italic, $smallcaps\n";
        	 $FONTSIZE = $fs;
        	 if(strrchr($fs, ".") === false)
        	 	$FONTSIZE .= ".0";
        	 else
        	 	$FONTSIZE .= "0";
        	 	
        	 $B = ($bold=="true")?"B":"";
        	 $I = ($italic=="true")?"I":"";
        	 $M = ($smallcaps=="true")?"M":"";

        	 $B1 = ($bold=="true")?"bold":"";
        	 $I1 = ($italic=="true")?"italics":"";
        	 $M1 = ($smallcaps=="true")?"smallcaps":"";

        	 $FONTTYPE = "sans-serif";
        	 $A = "A";
        	 if($ff == "Times New Roman"){
        	 	$FONTTYPE = "serif";
        	 	$A = "";
        	 }
        	 
        	 $ID = "TS_".$FONTSIZE."_".$B.$I.$M.$A;
        	 $FONTSTYLE = "$B1 $I1 $M1";
			if(isset($this->altoStylesMap[$ID])==null){
	            $altoTextStyle = $this->_altoDom->createElementNS(self::ALTO_NS, 'TextStyle');
	            $this->_altoStyle->appendChild($altoTextStyle);
	            $altoTextStyle->setAttribute('ID', $ID);
	            $altoTextStyle->setAttribute('FONTSIZE', $FONTSIZE);
	            $altoTextStyle->setAttribute('FONTSTYLE', $FONTSTYLE);
	            $altoTextStyle->setAttribute('FONTTYPE', $FONTTYPE);
	            $altoTextStyle->setAttribute('FONTWIDTH', "proportional");
				$this->altoStylesMap[$ID] = $ID;	 				
			}        	    	 
        	 $this->_addStrings($altoTextLine, $abbyyFormatting, $ID)	;
        	 //echo "$ID, $FONTSIZE, $FONTSTYLE, $FONTTYPE\n";
        }
 	}

    /**
     * Add ALTO String Elements
     * @param DOMElement $altoTextLine ALTO TextLine element 
     * @param DOMElement $abbyyFormatting AbbyyFinereader formatting element 
     * @param DOMElement $styleID unique id of the style 
     */
    protected function _addStrings($altoTextLine, $abbyyFormatting, $styleID) 
    {
        $abbyyCharParams = $abbyyFormatting->getElementsByTagName('charParams');
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
                    // if last character of a string, set height and width
                    $r = $abbyyCharParams->item($c)->getAttribute('r');
                    $b = $abbyyCharParams->item($c)->getAttribute('b');
                    
                    $hpos = $l;
                    $vpos = $t;
                    $height = $b - $t;
                    $width  = $r - $l;
                    
                    $altoString = $this->_altoDom->createElementNS(self::ALTO_NS, 'String');
                    $altoTextLine->appendChild($altoString);
                    $altoString->setAttribute('ID', "TB.{$this->docName}.{$this->_textBlockCount}_{$this->_textLineCount}_{$this->_stringCount}");
                    $altoString->setAttribute('CONTENT', $string);
                    $altoString->setAttribute('HPOS', $hpos);
                    $altoString->setAttribute('VPOS', $vpos);
                    $altoString->setAttribute('HEIGHT', $height);
                    $altoString->setAttribute('WIDTH', $width);
                    $altoString->setAttribute('STYLEREFS', $styleID);

                    $altoString = $this->_altoDom->createElementNS(self::ALTO_NS, 'SP');
                    $altoTextLine->appendChild($altoString);
                    $altoString->setAttribute('HPOS', $hpos);
                    $altoString->setAttribute('VPOS', $vpos+$width);
                    $altoString->setAttribute('HEIGHT', $height);
                    $altoString->setAttribute('WIDTH', "1.0");
                    
                    $l = null;
                    $t = null;
                    $r = null;
                    $b = null;
                    
                    $string = '';
                    $c++;
                    $this->_stringCount++;
                    
                //}
            }
        }
        
    }

    /**
     * Add ALTO Document Header
     * @param integer $resolution Abbyy resolution
     */
    protected function _addAltoHeader($resolution) 
    {
        $alto = $this->_altoDom->createElementNS(self::ALTO_NS, 'alto');
        $this->_altoDom->appendChild($alto);
        $alto->setAttribute(
                            'xsi', 
                            'http://www.w3.org/2001/XMLSchema-instance');
        $alto->setAttribute(
                            'alto', 
                            'http://www.loc.gov/standards/alto http://www.loc.gov/standards/alto/alto-v2.0.xsd');
        
        
        $description = $this->_altoDom->createElementNS(self::ALTO_NS, 'Description');
        $alto->appendChild($description);
        
        $measurementUnit = $this->_altoDom->createElementNS(self::ALTO_NS, 'MeasurementUnit', "inch".$resolution);
        
        $description->appendChild($measurementUnit);
        
        $sourceImageInformation = $this->_altoDom->createElementNS(self::ALTO_NS, 'sourceImageInformation');
        $description->appendChild($sourceImageInformation);
    
        $filename = basename($this->_abbyyFilename);
        $fileName = $this->_altoDom->createElementNS(self::ALTO_NS, 'fileName', $filename);
        $sourceImageInformation->appendChild($fileName);
        
        $ocrProcessing = $this->_altoDom->createElementNS(self::ALTO_NS, 'OCRProcessing');
        $description->appendChild($ocrProcessing);
        $ocrProcessing->setAttribute('ID', 'OCR_1');
        
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
        return $layout;
    }

    /**
     * Add ALTO Page Elements
     * @param mixed $id Page ID 
     * @param mixed $height Page Height in pixels 
     * @param mixed $width Page Width in pixels 
     * @param mixed $physicalImageNr Page Physical Image Number
     * @param mixed $quality Page Quality Information 
     */    
    protected function _addPage($id, $height, $width, $physicalImageNr, $layout) 
    {    
        $page = $this->_altoDom->createElementNS(self::ALTO_NS, 'Page');
        $layout->appendChild($page);
        $page->setAttribute('ID', $id);
        $page->setAttribute('HEIGHT', $height);
        $page->setAttribute('WIDTH', $width);
        $page->setAttribute('PHYSICAL_IMG_NR', $physicalImageNr);
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
if(!isset($argv[1])){
	echo "Source document name is missing";
	exit();
}
$output = "";

if(!isset($argv[2]))
	$output = "alto_".$argv[1];
else 
	$output = $argv[2]	;
$abbyyToAlto->convert($argv[1], $output);
