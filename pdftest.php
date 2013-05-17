<?php
define( 'ZEND_PATH', '../zf1/library' ); // Obviously you'll want to set ZEND_PATH to point to the folder in which your Zend folder resides.
set_include_path( ZEND_PATH . PATH_SEPARATOR . get_include_path());
require_once ZEND_PATH . '/Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();

require_once 'Wrap_Pdf.php';

$pdf = new Wrap_Pdf();
$pdf->setProperties( 'Sane_Pdf Example Document', $subject = 'Simplifying PDF Production with Zend_Pdf', $author = 'James Gordon' );

$pdf->addPage( 'Writing Text' );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 1 - WRITING TEXT' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( file_get_contents( 'resources/p1.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/p2.txt' ));
$pdf->setStyle(3);
$pdf->writeText( file_get_contents( 'resources/p3.txt' ));
$pdf->setStyle(4);
$pdf->writeText( file_get_contents( 'resources/p4.txt' ));
$pdf->setStyle(2);
$pdf->writeText( file_get_contents( 'resources/p5.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/p6.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/p7.txt' ));

$pdf->addPage( 'Automatic Page Breaking' );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 2 - AUTOMATIC PAGE BREAKING' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( file_get_contents( 'resources/l1.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/l2.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/l3.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/l4.txt' ));
$pdf->ln();
$pdf->ln();
$pdf->writeText( file_get_contents( 'resources/l4.txt' ));

$pdf->addPage( 'Including Images' );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 3 - INCLUDING IMAGES' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( "If we don't provide the width parameter, drawGraphic() will simply render the graphic the entire " .
                 'width of the page (ie. inside the margins).' );
$pdf->ln();

$pdf->drawGraphic( 'resources/GeoMonkey-PDF.jpg' );
$pdf->ln();
$pdf->writeText( 'Alternatively, if we include a width (in millimetres of course), then drawGraphic() will render the ' .
                 'image accordingly. Obviously this method could be further extended to include centering, etc.' );
$pdf->ln();
$pdf->drawGraphic( 'resources/GeoMonkey-PDF.jpg', 50 );
$pdf->ln();
$pdf->writeText( 'It is also worth noting that Sane_Pdf keeps track of which images have already been added to the ' .
                 'document and avoids loading up duplicate images in order to minimise the file size. As an example, ' .
                 'the incremental cost of rendering the monkey a second time is only 103 bytes, as the document ' .
                 'simply contains an additional reference to the original image with new placement information.' );

$pdf->addPage( 'Other Neat Stuff' );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 4 - DRAWING BOXES' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( 'This is a very rudimentary example of drawing boxes.' );
$pdf->ln();
$y = $pdf->getY();

for( $c = 0; $c < 30; $c += 5 )
{
    $pdf->drawRectangle( 20 + $c, $y + $c, 155 + $c, $y + 20 + $c, 'grey', 'green' );
}

$pdf->setY( $y + 30 + $c );

$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 5 - DRAWING LINES' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( 'A common requirement is to be able to draw ruled lines on a section of the page for notes.' );
$pdf->ln();
$pdf->drawLines( 10 );
$pdf->ln();

$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 6 - ROTATED TEXT' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( 'Rotating text can be tricky, unless you rotate the page around the point at which you are intending ' .
                 'to write the text - then its easy! :-)' );
$pdf->ln();
$pdf->writeRotatedText( 'This text is going up at 20 degrees', 30, 225, 20 );
$pdf->writeRotatedText( 'This text is going down at 20 degrees', 120, 205, -20 );
$pdf->writeRotatedText( 'This text is going straight up', 200, 240, 90 );
$pdf->writeRotatedText( 'This text is going straight down', 10, 190, -90 );
$pdf->writeRotatedText( 'And this is upside down!!!', 120, 230, 180 );

$pdf->setY( 250 );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 7 - ADDING LINKS' );
$pdf->ln();
$pdf->setStyle(2);
$pdf->writeText( 'Unfortunately when adding a link with Zend_Pdf you end up with a black border around the area that ' .
                 'was defined as clickable. I am yet to find a way of simply rendering a snippet of text as a link. ' .
                 'You also need to know the precise co-ordinates of the area you would like to make clickable, ' .
                 'which kind of breaks the encapsulation I was trying to achieve, but anyway...' );
$pdf->addLink( 83, 255, 99, 260, 'http://framework.zend.com/manual/en/zend.pdf.html' );


$pdf->addPage( 'Dumping Files' );
$pdf->setStyle(1);
$pdf->writeText( 'DEMONSTRATION 8 - DUMPING FILES' );
$pdf->ln();
$pdf->setStyle(0);
$pdf->writeLines( file_get_contents( __FILE__ ));

$pdf->output( 'pdf_demo.pdf' );
passthru( 'open -a Preview pdf_demo.pdf' ); // For Mac users only