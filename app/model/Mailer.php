<?php
namespace App\Model;

use Nette;

class Mailer
{
	use Nette\SmartObject;
    
    /**
     * @var Nette\Database\Context
     */
    private $database;

    
    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }


    public static function getMailer()
    {
    	return new Nette\Mail\SmtpMailer([
		    'host' => 'smtp.gmail.com',
		    'username' => 'vanco.dano@gmail.com',
		    'password' => 'hwivfcjfpfkfucpw',
            'secure' => 'ssl',
            'port' => 465
		]);
    }
}