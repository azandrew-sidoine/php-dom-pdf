<?php

namespace Drewlabs\Dompdf;

use function Drewlabs\Dompdf\Proxy\DomPdf;

class Factory implements FactoryInterface
{
    public function create($options = [])
    {
        return DomPdf((function () use($options) {
            if (!is_array($options) || empty($options)) {
                $defaults = require __DIR__ . '/../default.php';
                foreach ($defaults as $key => $value) {
                    $key = strtolower(str_replace('DOMPDF_', '', $key));
                    $options[$key] = $value;
                }
            }
            return $options ?? [];
        }));
    }
}
