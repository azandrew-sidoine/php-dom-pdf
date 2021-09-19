<?php

namespace Drewlabs\Core\Dompdf;

interface PdfFactory
{


    /**
     * Creates the instance of the DomPfable interface
     * @param array $options
     * 
     * @return self
     */
    public function make($options = []);

    /**
     * Resolve the created instance
     * 
     * @return DomPdfable
     */
    public function resolve();
}