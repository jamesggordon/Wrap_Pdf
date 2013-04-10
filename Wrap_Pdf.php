<?php
class Wrap_Pdf
{
    private $zpdf;
    private $pages = array();
    private $tocEntries = array();
    private $currentPage = -1;
    private $cx;                // This represents the current Y-coordinate of the cursor in millimetres
    private $cy;                // This represents the current Y-coordinate of the cursor in millimetres
    private $colours = array();
    private $layout;
    private $paperSize;
    private $paperWidth;
    private $paperHeight;
    private $autoPageBreak = true;
    private $fontRegular;
    private $fontItalic;
    private $fontBold;
    private $fontBoldItalic;
    private $fontLight;
    private $fontLightItalic;

    // The following attributes keep track of the current settings for the page.
    private $currentFont = null;
    private $currentFontSize = 0;
    private $currentFontSpacing = 0; // This might be called "line spacing" or "leading" (the latter being the more
                                     // technically correct term in the publishing world), however both of those terms
                                     // measure the distance between lines relative to the font size. We, however, are
                                     // measuring the spacing in terms of mm between the top of one printed line and
                                     // the top of the next printed line, hence the creation of a different label.

    private $currentLineWidth = 0;
    private $currentLineColour = '';
    private $currentFillColour = '';
    private $currentMarginLeft = 20;
    private $currentMarginRight = 20;
    private $currentMarginTop = 20;
    private $currentMarginBottom = 20;

    // Cache images so that if they are loaded more than once, we re-use them
    private $imageCache = array();

    public function __construct( $layout = 'P', $paperSize = 'A4' )
    {
        $this->layout = $layout;          // 'P' for Portrait, 'L' for Landscape
        $this->paperSize = $paperSize;    // 'A4' or 'Letter'

        if ( $paperSize == 'Letter' )
        {
            if ( $layout == 'P' )
            {
                $this->paperWidth = 216;  // millimetres
                $this->paperHeight = 279; // millimetres
                $this->paperSizeDetail = Zend_Pdf_Page::SIZE_LETTER;
            }
            else
            {
                $this->paperWidth = 279;  // millimetres
                $this->paperHeight = 216; // millimetres
                $this->paperSizeDetail = Zend_Pdf_Page::SIZE_LETTER_LANDSCAPE;
            }
        }
        else if ( $paperSize == 'A4' )
        {
            if ( $layout == 'P' )
            {
                $this->paperWidth = 210;  // millimetres
                $this->paperHeight = 297; // millimetres
                $this->paperSizeDetail = Zend_Pdf_Page::SIZE_A4;
            }
            else
            {
                $this->paperWidth = 297;  // millimetres
                $this->paperHeight = 210; // millimetres
                $this->paperSizeDetail = Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
            }
        }
        else if ( $paperSize == 'BusinessCard' )
        {
            $this->paperWidth = 89;
            $this->paperHeight = 51;
            $this->paperSizeDetail = '252:144:';
        }
        else
        {
            throw new Exception( 'Unknown paper size requested' );
        }

        // Create the actual PDF object
        $this->zpdf = new Zend_Pdf();

        // Set up fonts
        $this->fontRegular = Zend_Pdf_Font::fontWithPath('/Users/james/ws/wip/font/Dax-Regular.ttf');
        $this->fontItalic = Zend_Pdf_Font::fontWithPath('font/JUICE_Italic.ttf');
        $this->fontBold = Zend_Pdf_Font::fontWithPath('font/JUICE_Bold.ttf');
        $this->fontBoldItalic = Zend_Pdf_Font::fontWithPath('font/JUICE_Bold_Italic.ttf');
        $this->fontLight = Zend_Pdf_Font::fontWithPath('font/JUICE_Light.ttf');
        $this->fontLightItalic = Zend_Pdf_Font::fontWithPath( 'font/JUICE_Light_Italic.ttf');

        // Set up colours - and here's a BIG tip: Zend_Pdf_Color_Rgb() expects values from 0 to 1, NOT 0 to 255 !!!
        $this->colours['black'] = new Zend_Pdf_Color_Rgb( 0, 0, 0 );
        $this->colours['white'] = new Zend_Pdf_Color_Rgb( 1, 1, 1 );
        $this->colours['grey'] = new Zend_Pdf_Color_Rgb( 0.3, 0.3, 0.3 );
        $this->colours['blue'] = new Zend_Pdf_Color_Rgb( 0, 0.5, 0.8 );
        $this->colours['green'] = new Zend_Pdf_Color_Rgb( 0.6, 0.8, 0.7 );
    }

    public function setProperties( $title, $subject = '', $author = '', $producer = '', $creator = '', $keywords = '' )
    {
        // See the metadata section (10.2) in the following file for the precise definition of each of these fields:
        // http://partners.adobe.com/public/developer/en/pdf/PDFReference16.pdf#page=794
        $this->zpdf->properties['Title'] = $title;
        $this->zpdf->properties['Subject'] = $subject;
        $this->zpdf->properties['Author'] = $author;     // The name of the person who created the document.

        $this->zpdf->properties['Producer'] = $producer; // If  the document was converted to PDF from another format,
                                                         // the name of the application that converted it to PDF. This
                                                         // should theoretically be left blank most of the time, as the
                                                         // PDF is being created from scratch, not converted from some
                                                         // other document.

        $this->zpdf->properties['Creator'] = $creator; // If the document was converted to PDF from another format, the
                                                       // name of the application that created the original document
                                                       // from which it was converted. This should theoretically be
                                                       // left blank most of the time, as the PDF is being created from
                                                       // scratch, not converted from some other format.

        $this->zpdf->properties['Keywords'] = $keywords;

        $now = 'D:' . date( "YmdHis", time()) . 'Z';
        $this->zpdf->properties['CreationDate'] = $now;
        $this->zpdf->properties['ModDate'] = $now;
        $this->zpdf->properties['Trapped'] = 'False'; // See this page for information on trapping: http://bit.ly/aKLDYZ
    }

    public function setMargins( $left, $right = 0, $top = 0, $bottom = 0 )
    {
        $this->currentMarginLeft = $left;

        if ( $right )
        {
            $this->currentMarginRight = $right;
        }

        if ( $top )
        {
            $this->currentMarginTop = $top;
        }

        if ( $bottom )
        {
            $this->currentMarginBottom = $bottom;
        }
    }

    public function addPage( $tocEntry = '' )
    {
        $this->currentPage++;
        $this->zpdf->pages[$this->currentPage] = new Zend_Pdf_Page( $this->paperSizeDetail );

        if ( $tocEntry )
        {
            $this->tocEntries[] = array( $tocEntry, $this->currentPage );
        }

        $this->cx = $this->currentMarginLeft;
        $this->cy = $this->currentMarginTop;

        // These things need to be reset each time we create a new page.
        $this->currentFont = null;
        $this->currentFontSize = 0;
        $this->currentLineWidth = 0;
        $this->currentLineColour = '';
        $this->currentFillColour = '';
    }

    public function setStyle( $style )
    {
        switch( $style )
        {
            case 1:
                $this->setFont( $this->fontBold, 16, 9, 'blue' );
                break;

            case 2:
                $this->setFont( $this->fontRegular, 24, 6, 'grey' );
                break;

            case 3:
                $this->setFont( $this->fontBold, 9, 6, 'grey' );
                break;

            case 4:
                $this->setFont( $this->fontItalic, 9, 6, 'grey' );
                break;

            default:
                $this->setFont( $this->fontRegular, 9, 6, 'black' );
        }
    }

    private function setFont( $font, $size, $spacing, $colour )
    {
        if ( $font != $this->currentFont || $size != $this->currentFontSize )
        {
            $this->zpdf->pages[$this->currentPage]->setFont( $font, $size );
            $this->currentFont = $font;
            $this->currentFontSize = $size;
        }

        $this->setFillColour( $colour );
        $this->currentFontSpacing = $spacing;
    }

    private function setFillColour( $colour )
    {
        if ( !isset( $this->colours[ $colour ] ))
        {
            echo 'Unknown colour requested for fill: ', $colour, "\n";
            exit;
        }

        if ( $colour != $this->currentFillColour )
        {
            $this->zpdf->pages[$this->currentPage]->setFillColor( $this->colours[ $colour ]);
            $this->currentFillColour = $colour;
        }
    }

    private function setLineColour( $colour )
    {
        if ( !isset( $this->colours[ $colour ] ))
        {
            echo 'Unknown colour requested for line: ', $colour, "\n";
            exit;
        }

        if ( $colour != $this->currentLineColour )
        {
            $this->zpdf->pages[$this->currentPage]->setLineColor( $this->colours[ $colour ]);
            $this->currentLineColour = $colour;
        }
    }

    public function setLineWidth( $width )
    {
        if ( $width != $this->currentLineWidth )
        {
            $this->zpdf->pages[$this->currentPage]->setLineWidth( $width );
        }
    }

    public function drawRectangle( $x1, $y1, $x2, $y2, $outline = 'black', $fill = 'white' )
    {
        $x1 = $this->mmToPoints( $x1 );
        $x2 = $this->mmToPoints( $x2 );
        $y1 = $this->mmToPoints( $this->paperHeight - $y1 );
        $y2 = $this->mmToPoints( $this->paperHeight - $y2 );

        $this->setLineColour( $outline );
        $this->setFillColour( $fill );

        $this->zpdf->pages[$this->currentPage]->drawRectangle( $x1, $y1, $x2, $y2 );
    }

    public function output( $filename = '' )
    {
        $entries = count( $this->tocEntries );

        if ( $entries )
        {
            $this->zpdf->outlines[0] = Zend_Pdf_Outline::create( 'Table of Contents', null );

            for( $c = 0; $c < $entries; $c++ )
            {
                $pageNo = $this->tocEntries[$c][1];
                $destination{$c} = Zend_Pdf_Destination_Fit::create( $this->zpdf->pages[ $pageNo ] );
                $this->zpdf->setNamedDestination( 'page_' . $pageNo, $destination{$c} );
                $this->zpdf->outlines[0]->childOutlines[] = Zend_Pdf_Outline::create( $this->tocEntries[$c][0], $this->zpdf->getNamedDestination( 'page_' . $pageNo ));
            }
        }

        if ( $filename )
        {
            $this->zpdf->save( $filename );
        }
        else
        {
            return $this->zpdf->render();
        }
    }

    // Just in case you don't want this class to automatically add page breaks...
    public function setAutoPageBreak( $auto = true )
    {
        $this->autoPageBreak = $auto;
    }

    // One thing that can make writing rotated text complicated is the fact that when you rotate the page, the entire
    // co-ordinate system rotates around the point that you nominate when calling the rotate() method. The trick, then,
    // is to rotate the page around the actual co-ordinates where you would like the text to start. By definition, that
    // point won't move during the rotation process, as that point is at the centre. So then you can send those
    // co-ordinates to the drawText() method and be sure that your text will actually appear at that point. This saves
    // some otherwise very tricky maths!
    public function writeRotatedText( $text, $sx, $sy, $angle )
    {
        $px = $this->mmToPoints( $sx );
        $py = $this->mmToPoints( $this->paperHeight - $sy );

        $this->zpdf->pages[$this->currentPage]->rotate( $px, $py, deg2rad( $angle ))
                                              ->drawText( $text, $px, $py )
                                              ->rotate( $px, $py, deg2rad( -$angle ));
    }

    /**
     * Simple method to write a block of text, wrapping lines according to the current margin settings.
     *
     * Please note that this method starts by splitting up the incoming text into individual lines and then calling the
     * writeText() method to do the actual rendering. The only real thing this adds over calling writeText() directly
     * is that leading spaces on ALL lines will be preserved.
     *
     * @param string $text The line of text to be written.
     */
    public function writeLines( $text )
    {
        $lines = explode( "\x0a", $text );

        foreach( $lines as $line )
        {
            $this->writeText( $line );
            $this->ln();
        }
    }

    /**
     * Simple method to write a block of text, wrapping lines according to the current margin settings. Note that it
     * does NOT render any carriage returns unless it needs to wrap. Hence, you will need to call the ln() method to
     * add one or more carriage returns after the paragraph has been written.
     *
     * This method was derived from part of the class that 'storeman' contributed to the Zend Framework docs. See the
     * bottom of this page for details: http://framework.zend.com/manual/en/zend.pdf.pages.html
     *
     * @param string $text The line of text to be written.
     */
    public function writeText( $text )
    {
        $lineText = '';

        for( $i = 0, $m = strlen( $text ); $i < $m && $text[$i] == ' '; $i++ )
        {
            $lineText .= ' ';
        }

        preg_match_all('/([^\s]*\s*)/i', $text, $matches);
        $words = $matches[1];

        $lineWidth = $this->getStringWidth( $lineText );
        $width = $this->paperWidth - $this->currentMarginRight - $this->cx;

        foreach( $words as $word )
        {
            // If this method has been called by writeLines() then there won't be any carriage returns. However, if the
            // method is being called directly then there may well be some stray carriage returns in there, which we
            // will strip out.
            $word = str_replace( "\x0a", ' ', $word );

            $wordWidth = $this->getStringWidth( $word );

            if ( $lineWidth + $wordWidth < $width )
            {
                $lineText .= $word;
                $lineWidth += $wordWidth;
            }
            else
            {
                // At this point we simply need to render the line and add a carriage return
                $this->zpdf->pages[$this->currentPage]->drawText( $lineText,
                                                                  $this->mmToPoints( $this->cx ),
                                                                  $this->mmToPoints( $this->paperHeight - $this->cy ));
                $this->ln();
                $width = $this->paperWidth - $this->currentMarginRight - $this->cx;

                // And now we prime our strings ready for the next iteration through the loop
                $lineText = $word;
                $lineWidth = $wordWidth;
            }
        }

        // At this point we're finishing off the rendering of a line that does NOT need a carriage return
        $this->zpdf->pages[$this->currentPage]->drawText( $lineText,
                                                          $this->mmToPoints( $this->cx ),
                                                          $this->mmToPoints( $this->paperHeight - $this->cy ));

        $this->cx += $this->getStringWidth( $lineText );
    }

    // This is basically a copy of Zend_Barcode_Renderer_Pdf->widthForStringUsingFontSize(), the main differences being
    // that (A) it calculates the width based on the current font settings and (B) it returns the width in mm, not points.
    private function getStringWidth( $string )
    {
        $drawingString = iconv( '', 'UTF-16BE', $string );
        $characters = array();

        for ( $i = 0; $i < strlen( $drawingString ); $i++ )
        {
            $characters[] = ( ord( $drawingString[$i++]) << 8 ) | ord( $drawingString[$i] );
        }

        $font = $this->currentFont;
        $glyphs = $font->glyphNumbersForCharacters( $characters );
        $widths = $font->widthsForGlyphs( $glyphs );
        $stringWidth = ( array_sum( $widths ) / $font->getUnitsPerEm()) * $this->currentFontSize;

        return $this->pointsToMm( $stringWidth );
    }

    // Move the cursor to the left margin and move down the standard amount or some arbitrary amount
    public function ln( $h = 0 )
    {
        $this->cx = $this->currentMarginLeft;

        if ( $h )
        {
            $this->cy += $h;
        }
        else
        {
            $this->cy += $this->currentFontSpacing;
        }

        if ( $this->autoPageBreak && $this->paperHeight - $this->cy < $this->currentMarginBottom  )
        {
            $f = $this->currentFont;
            $s = $this->currentFontSize;
            $p = $this->currentFontSpacing;
            $c = $this->currentFillColour;
            $this->addPage();
            $this->setFont( $f, $s, $p, $c );
        }
    }

    public function addLink( $x1, $y1, $x2, $y2, $url )
    {
        $x1 = $this->mmToPoints( $x1 );
        $x2 = $this->mmToPoints( $x2 );
        $y1 = $this->mmToPoints( $this->paperHeight - $y1 );
        $y2 = $this->mmToPoints( $this->paperHeight - $y2 );

        $target = Zend_Pdf_Action_URI :: create( $url );
        $annotation = Zend_Pdf_Annotation_Link :: create( $x1, $y1, $x2, $y2, $target );
        $this->zpdf->pages[$this->currentPage]->attachAnnotation( $annotation );
    }

    public function drawLines( $lines )
    {
        $this->setLineWidth( 0.25 );
        $this->setLineColour( 'grey' );
        $x1 = $this->mmToPoints( $this->currentMarginLeft );
        $x2 = $this->mmToPoints( $this->paperWidth - $this->currentMarginRight );

        for( $c = 0; $c < $lines && ( $this->cy < ( $this->paperHeight - $this->currentMarginBottom - 5 )); $c++, $this->cy += 7 )
        {
            $yl = $this->mmToPoints( $this->paperHeight - $this->cy );
            $this->zpdf->pages[$this->currentPage]->drawLine( $x1, $yl, $x2, $yl );
        }
    }

    public function drawGraphic( $filename, $width = 0 )
    {
        if ( $width == 0 )
        {
            // This mode renders an image the entire width of the main text area
            $this->cy += $this->image( $filename,
                                       $this->currentMarginLeft,
                                       $this->cy,
                                       $this->paperWidth - $this->currentMarginLeft - $this->currentMarginRight ) + 2;
        }
        else
        {
            $this->cy += $this->image( $filename, $this->currentMarginLeft, $this->cy, $width ) + 2;
        }
    }

    // This importance of this method is that it retains the aspect ratio when rendering the image.
    private function image( $filename, $x_mm, $y_mm, $w_mm = 0 )
    {
        $size = getimagesize( $filename );
        $width = $size[0];
        $height = $size[1];

        if ( $w_mm == 0 )
        {
            $w_mm = $this->pointsToMm( $width );
        }

        $h_mm = $height / $width * $w_mm;

        $x1 = $this->mmToPoints( $x_mm );
        $x2 = $this->mmToPoints( $x_mm + $w_mm );
        $y1 = $this->mmToPoints( $this->paperHeight - $y_mm - $h_mm );
        $y2 = $this->mmToPoints( $this->paperHeight - $y_mm );

        if ( !isset( $this->imageCache[$filename] ))
        {
            $this->imageCache[$filename] = Zend_Pdf_Image::imageWithPath( $filename );
        }

        $this->zpdf->pages[$this->currentPage]->drawImage( $this->imageCache[$filename], $x1, $y1, $x2, $y2 );

        return $h_mm;
    }

    // Convert from points to inches (there are 72 points to an inch) then from inches to mm (there are 25.4 mm per inch)
    private function pointsToMm( $points )
    {
        return $points / 72 * 25.4;
    }

    // Convert from mm to inches (there are 25.4mm to an inch) then from inches to points (there are 72 points per inch)
    private function mmToPoints( $mm )
    {
        return $mm / 25.4 * 72;
    }

    // Some convenience methods - just in case you want to move the cursor to some arbitrary position on the page
    public function getX()
    {
        return $this->cx;
    }

    public function setX( $x )
    {
        $this->cx = $x;
    }

    public function getY()
    {
        return $this->cy;
    }

    public function setY( $y )
    {
        $this->cy = $y;
    }
}
?>