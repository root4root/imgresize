<?php
namespace Root4root\ImgResize\Exception;

class BadResourceException extends \Exception
{
    public function __construct($reason)
    {
        parent::__construct($reason);
    }
}
