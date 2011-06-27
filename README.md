# li3 Clickatell

The Lithium PHP SMS Library for API.Clickatell.com

## Introduction

To use this library, you will need sign up for an account at www.clickatell.com. The easiest one to sign up for for testing purposes is their Small Business U.S. account which is just $25/mo. and supports Two-Way Messaging. When you signup, it currently does not prompt you for a credit card right away. You can signup for free and you get 10 texts. But once you validate your email they will ask for your credit card. This is optional and you can test without it. You will not be able to customize the message body unless you call and talk to them. If you need more than 10 texts thats when you enter your credit card number to get the full 500 quota.

## Installation

Copy this code to your ./libraries/ directory:

`git submodule add https://mikesmullin@github.com/mikesmullin/li3_clickatell.git ./libraries/li3_clickatell`

## Configuration

You will need 3-4 things, depending on what you want to do:

1. **Username** (you choose this during registration)
2. **Password** (you choose this during registration)
3. **API ID** (this is usually auto-generated and you can find it under Manage My Products; if not, you have to provision one through Clickatell web admin yourself)
4. **Two-Way Number** (only on select accounts; provisioned once you pay and displayed on Central Home page in Clickatell web admin)

Add this line to your ./app/config/bootstrap/libraries.php:

```php
<?php

	/* ... */

	Library::add('li3_clickatell');

?>
```

Add this line to your ./app/config/bootstrap/connections.php:

```php
<?php

/* ... */

/**
 * Clickatell API for SMS messaging.
 */
Connections::add('my_clickatell', array(
	'type'			=> 'http',
	'adapter'		=> 'Clickatell',
	'api_id'		=> '1234567',
	'api_username'	=> 'myuser',
	'api_password'	=> 'mypass',
	'from'			=> '18001234567'
));

?>
```

Create a new model ./app/models/Sms.php:

```php
<?php

namespace app\models;

class Sms extends \li3_clickatell\extensions\Model {

	protected $_meta = array(
		'name' => null,
		'title' => null,
		'class' => null,
		'source' => null,
		'connection' => 'my_clickatell',
		'initialized' => false
	);
}

?>
```

## Usage

To send a message:


```php
<?php

use app\models\Sms;

class UsersController extends \lithium\action\Controller {

	/* ... */

	public function send_text() {
		$sms_message_id = Sms::send('18001234567', 'this is a test of the li3_clickatell library');
		$status = Sms::query($sms_message_id);
		die($status);
	}
}

?>
```
