<?php

namespace Drewlabs\Core\Dompdf;

use function Drewlabs\Core\Dompdf\Proxy\DomPdf;

class DomPdfFactory implements PdfFactory
{
    /**
     *
     * @var DomPdfable
     */
    protected $pdf;

    /**
     * @inheritDoc
     */
    public function make($options = [])
    {
        return $this->setDomPdfInstance((function ($options_) {
            if (!is_array($options_) || empty($options_)) {
                $defaults = require __DIR__ . '/../default.php';
                foreach ($defaults as $key => $value) {
                    $key = strtolower(str_replace('DOMPDF_', '', $key));
                    $options_[$key] = $value;
                }
            }
            return $options_ ?? [];
        })($options ?? []));
    }

    private function setDomPdfInstance(array $options)
    {
        $this->pdf = DomPdf($options);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve()
    {
        return $this->pdf;
    }
}
