<?php

namespace Leadz\SyliusCmiPlugin\Cmi;

use CMI\CmiClient;

class CmiProdClient extends CmiClient
{
    const DEFAULT_API_BASE = 'https://payment.cmi.co.ma';

    public function redirect_post() {
        $url = self::DEFAULT_API_BASE.'/fim/est3Dgate';

        $html = "<html lang='en'>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html; charset=ISO-8859-9'>";
        $html .= "<meta http-equiv='Pragma' content='no-cache'>";
        $html .= "<meta http-equiv='Expires' content='now'>";
        $html .= "</head>";
        $html .="<body onload='closethisasap();'>";
        $html .="<form name='redirectpost' method='post' action='{$url}'>";
        if ( !is_null($this->getRequireOpts()) ) {
            foreach ($this->getRequireOpts() as $name => $value) {
                $html .= "<input type='hidden' name='{$name}' value='".trim($value)."'> ";
            }
        }
        $html .="</form>";
        $html .="<script type='text/javascript'>";
        $html .= "function closethisasap() { document.forms['redirectpost'].submit(); }";
        $html .= "</script>";
        $html .="</body></html>";
        echo $html;
        exit();
    }
}
