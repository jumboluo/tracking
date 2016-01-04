<?php
/**
 * PHPExcel
 *
 * Copyright (c) 2006 - 2012 PHPExcel
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category	PHPExcel
 * @package		PHPExcel_Writer
 * @copyright	Copyright (c) 2006 - 2012 PHPExcel (http://www.codeplex.com/PHPExcel)
 * @license		http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt	LGPL
 * @version		1.7.8, 2012-10-12
 */


/** Require mPDF library */
$pdfRendererClassFile = PHPExcel_Settings::getPdfRendererPath() . '/mpdf.php';
if (file_exists($pdfRendererClassFile)) {
	require_once $pdfRendererClassFile;
} else {
	throw new Exception('Unable to load PDF Rendering library');
}

/**
 * PHPExcel_Writer_PDF_mPDF
 *
 * @category	PHPExcel
 * @package		PHPExcel_Writer
 * @copyright	Copyright (c) 2006 - 2012 PHPExcel (http://www.codeplex.com/PHPExcel)
 */
class PHPExcel_Writer_PDF_mPDF extends PHPExcel_Writer_PDF_Core implements PHPExcel_Writer_IWriter {
	/**
	 * Create a new PHPExcel_Writer_PDF
	 *
	 * @param 	PHPExcel	$phpExcel	PHPExcel object
	 */
	public function __construct(PHPExcel $phpExcel) {
		parent::__construct($phpExcel);
	}
	
	/**
	 * 内嵌的JavaScript
	 *
	 * @var string
	 */
	protected $_script;
	
	public function setScript($code) {
		$this->_script = $code;
	}
	
	protected $_printPaperSize = 'A4';
	protected $_printOrientation = 'P';
	protected $_printHeaderO = '';
	protected $_printFooterO = '';
	protected $_printHeaderE = '';
	protected $_printFooterE = '';
	protected $_printTmargin = '16';
	protected $_printBmargin = '16';
	protected $_printLmargin = '15';
	protected $_printRmargin = '15';
	protected $_printHmargin = '14';
	protected $_printFmargin = '14';
	protected $_mirrorMargins = false;
	protected $_printParamsSetted = false;
	
	public function setPrintParams($paperSize, $orientation, $header1, $footer1, $header2, $footer2, $tmargin, $bmargin, $lmargin, $rmargin, $hmargin, $fmargin, $mirrormargins, $customPaperSizeWidth=210, $customPaperSizeHeight=297) {
		if(strcasecmp($orientation, 'p')==0
				|| strcasecmp($orientation, 'l')==0
				|| strcasecmp($orientation, 'portrait')==0
				|| strcasecmp($orientation, 'landscape')==0){
			$this->_printOrientation = strtoupper(substr($orientation, 0, 1));
		}

		switch ($paperSize) {
			case '16KAI':
				$this->_printPaperSize = array(184.00, 260.00);
				break;
			case '32KAI':
				$this->_printPaperSize = array(130.00, 184.00);
				break;
			case '32KAIB':
				$this->_printPaperSize = array(140.00, 203.00);
				break;
			case 'ZL':
				$this->_printPaperSize = array(120.00, 230.00);
				break;
			case 'DL':
				$this->_printPaperSize = array(110.00, 220.00);
				break;
			case 'CUSTOM':
				$this->_printPaperSize = array($customPaperSizeWidth, $customPaperSizeHeight);
				break;
			default:
				if(!empty($paperSize)) $this->_printPaperSize = strtoupper($paperSize).($this->_printOrientation=='L' ? '-L' : '');
				break;
		}

		if(!empty($header1)) {
			$this->_printHeaderO = $header1;
		}
		if(!empty($footer1)) {
			$this->_printFooterO = $footer1;
		}
		if(!empty($header2)) {
			$this->_printHeaderE = $header2;
		}
		if(!empty($footer2)) {
			$this->_printFooterE = $footer2;
		}
		if(!empty($tmargin) && is_numeric($tmargin) && $tmargin>0) {
			$this->_printTmargin = $tmargin;
		}
		if(!empty($bmargin) && is_numeric($bmargin) && $bmargin>0) {
			$this->_printBmargin = $bmargin;
		}
		if(!empty($lmargin) && is_numeric($lmargin) && $lmargin>0) {
			$this->_printLmargin = $lmargin;
		}
		if(!empty($rmargin) && is_numeric($rmargin) && $rmargin>0) {
			$this->_printRmargin = $rmargin;
		}
		if(!empty($hmargin) && is_numeric($hmargin) && $hmargin>0) {
			$this->_printHmargin = $hmargin;
		}
		if(!empty($fmargin) && is_numeric($fmargin) && $fmargin>0) {
			$this->_printFmargin = $fmargin;
		}
		
		$this->_mirrorMargins = $mirrormargins;
		
		$this->_printParamsSetted = true;
	}

	/**
	 * Save PHPExcel to file
	 *
	 * @param 	string 		$pFileName
	 * @throws 	Exception
	 */
	public function save($pFilename = null) {
		// garbage collect
		$this->_phpExcel->garbageCollect();

		$saveArrayReturnType = PHPExcel_Calculation::getArrayReturnType();
		PHPExcel_Calculation::setArrayReturnType(PHPExcel_Calculation::RETURN_ARRAY_AS_VALUE);

		// Open file
		$fileHandle = fopen($pFilename, 'w');
		if ($fileHandle === false) {
			throw new Exception("Could not open file $pFilename for writing.");
		}
		// Set PDF
		$this->_isPdf = true;
		// Build CSS
		$this->buildCSS(true);
		
		if(!$this->_printParamsSetted) {
			// Default PDF paper size
			$paperSize = 'A4';
	
			// Check for paper size and page orientation
			if (is_null($this->getSheetIndex())) {
				$orientation = ($this->_phpExcel->getSheet(0)->getPageSetup()->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
				$printPaperSize = $this->_phpExcel->getSheet(0)->getPageSetup()->getPaperSize();
				$printMargins = $this->_phpExcel->getSheet(0)->getPageMargins();
			} else {
				$orientation = ($this->_phpExcel->getSheet($this->getSheetIndex())->getPageSetup()->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
				$printPaperSize = $this->_phpExcel->getSheet($this->getSheetIndex())->getPageSetup()->getPaperSize();
				$printMargins = $this->_phpExcel->getSheet($this->getSheetIndex())->getPageMargins();
			}
			$this->setOrientation($orientation);
	
			//	Override Page Orientation
			if (!is_null($this->getOrientation())) {
				$orientation = ($this->getOrientation() == PHPExcel_Worksheet_PageSetup::ORIENTATION_DEFAULT) ?
					PHPExcel_Worksheet_PageSetup::ORIENTATION_PORTRAIT : $this->getOrientation();
			}
			$orientation = strtoupper($orientation);
	
			//	Override Paper Size
			if (!is_null($this->getPaperSize())) {
				$printPaperSize = $this->getPaperSize();
			}
	
			if (isset(self::$_paperSizes[$printPaperSize])) {
				$paperSize = self::$_paperSizes[$printPaperSize];
			}
			
			if(is_string($paperSize)){
				$paperSize = strtoupper($paperSize.(substr($orientation,0,1) == 'L' ? '-L' : ''));
			}
			
			if($printMargins) {	//$printMargins是英寸制，要转换成毫米
				$tmargin = 25.4 * $printMargins->getTop();
				$bmargin = 25.4 * $printMargins->getBottom();
				$lmargin = 25.4 * $printMargins->getLeft();
				$rmargin = 25.4 * $printMargins->getRight();
				$hmargin = 25.4 * $printMargins->getHeader();
				$fmargin = 25.4 * $printMargins->getFooter();
			}
		}
		else {
			$paperSize = $this->_printPaperSize;
			$orientation = $this->_printOrientation;
			$tmargin = $this->_printTmargin;
			$bmargin = $this->_printBmargin;
			$lmargin = $this->_printLmargin;
			$rmargin = $this->_printRmargin;
			$hmargin = $this->_printHmargin;
			$fmargin = $this->_printFmargin;
		}
		
		error_reporting(E_ALL ^ E_NOTICE); //当前的版本很多notice警告，屏蔽掉
		
		// Create PDF
		$pdf = new mpdf();
		$pdf->useAdobeCJK = true;		// Default setting in config.php
		$pdf->SetAutoFont(AUTOFONT_ALL);	//	AUTOFONT_CJK | AUTOFONT_THAIVIET | AUTOFONT_RTL | AUTOFONT_INDIC	// AUTOFONT_ALL

// 		Log::write("\ndefault pdf:\nT:".$pdf->tMargin."\nB:".$pdf->bMargin."\nL:".$pdf->DeflMargin."\nR:".$pdf->DefrMargin."\nH:".$pdf->margin_header."\nF:".$pdf->margin_footer."\n\n");

		$pdf->_setPageSize($paperSize, $orientation);
        $pdf->DefOrientation = $orientation;

        if(!empty($this->_printFooterO)) {
        	$pdf->SetHTMLFooter($this->_printFooterO);
        }
        if(!empty($this->_printHeaderO)) {
        	$pdf->SetHTMLHeader($this->_printHeaderO, 'O');
        }
		if(!empty($this->_printFooterE)) {
			$pdf->SetHTMLFooter($this->_printFooterE, 'E');
		}
		if(!empty($this->_printHeaderE)) {
			$pdf->SetHTMLHeader($this->_printHeaderE, 'E');
		}
		if(!empty($tmargin)) {
			$pdf->tMargin = $tmargin;
		}
		if(!empty($bmargin)) {
			$pdf->bMargin = $bmargin;
		}
		if(!empty($lmargin)) {
			$pdf->DeflMargin = $lmargin;
		}
		if(!empty($rmargin)) {
			$pdf->DefrMargin = $rmargin;
		}
		if(!empty($hmargin)) {
			$pdf->margin_header = $hmargin;
		}
		if(!empty($fmargin)) {
			$pdf->margin_footer = $fmargin;
		}
		
		$pdf->mirrorMargins = $this->_mirrorMargins;
		
        if(!empty($this->_script)) {
        	$pdf->SetJS($this->_script);
        }
        
        // Document info
		$pdf->SetTitle($this->_phpExcel->getProperties()->getTitle());
		$pdf->SetAuthor($this->_phpExcel->getProperties()->getCreator());
		$pdf->SetSubject($this->_phpExcel->getProperties()->getSubject());
		$pdf->SetKeywords($this->_phpExcel->getProperties()->getKeywords());
		$pdf->SetCreator($this->_phpExcel->getProperties()->getCreator());

		$pdf->WriteHTML(
			$this->generateHTMLHeader(false) .
			$this->generateSheetData() .
			$this->generateHTMLFooter()
		);

		// Write to file
		fwrite($fileHandle, $pdf->Output('','S'));

		// Close file
		fclose($fileHandle);

		PHPExcel_Calculation::setArrayReturnType($saveArrayReturnType);
	}

}
