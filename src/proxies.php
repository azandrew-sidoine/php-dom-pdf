<?php

namespace Drewlabs\Core\Dompdf\Proxy;

use Drewlabs\Core\Dompdf\Dompdf;
use Drewlabs\Core\Dompdf\DomPdfFactory;
use Drewlabs\Core\Dompdf\PathPrefixer;
use Drewlabs\Core\Dompdf\PdfFactory;
use Drewlabs\Core\Dompdf\DomPdfable;

/**
 * Proxy function to an instance of {@see PathPrefixer}
 * 
 * @param string $base 
 * @return PathPrefixer 
 */
function PathPrefixer(string $base)
{
    return new PathPrefixer($base);
}

/**
 * Proxy function to an instance of {@see PdfFactory}
 * 
 * @return PdfFactory 
 */
function PdfFactory()
{
    return new DomPdfFactory();
}

/**
 * Proxy function to an instance of {@see DomPdfable}
 * 
 * @param array $options 
 * @return DomPdfable 
 */
function DomPdf(array $options = [])
{
    return new DomPdf($options);
}