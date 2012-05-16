RollerworksMailBundle
=======================

This bundle provides Templating and Attachment Decorating for SwiftMailer with Symfony

### Template

The SwiftMailer template decorator, handles e-mail messages using the Symfony Templating Component.

### AttachmentDecorator

The SwiftMailer attachment decorator is similar to Template decorator, but instead it handles attachments.

## Installation

### Step 1: Using Composer (recommended)

To install RollerworksMailBundle with Composer just add the following to your
`composer.json` file:

```js
// composer.json
{
    // ...
    require: {
        // ...
        "rollerworks/mail-bundle": "master-dev"
    }
}
```

**NOTE**: Please replace `master-dev` in the snippet above with the latest stable
branch, for example ``2.0.*``.

Then, you can install the new dependencies by running Composer's ``update``
command from the directory where your ``composer.json`` file is located:

```bash
$ php composer.phar update
```

Now, Composer will automatically download all required files, and install them
for you. All that is left to do is to update your ``AppKernel.php`` file, and
register the new bundle:

```php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\MailBundle\RollerworksMailBundle(),
    // ...
);
```

### Step 1 (alternative): Using ``deps`` file (Symfony 2.0.x)

First, checkout a copy of the code. Just add the following to the ``deps``
file of your Symfony Standard Distribution:

```ini
[RollerworksMailBundle]
    git=http://github.com/rollerscapes/RollerworksMailBundle.git
    target=/bundles/Rollerworks/Bundle/MailBundle
```

**NOTE**: You can add `version` tag in the snippet above with the latest stable
branch, for example ``version=origin/2.0``.

Then register the bundle with your kernel:

```php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\MailBundle\RollerworksMailBundle(),
    // ...
);
```

Make sure that you also register the namespace with the autoloader:

```php
<?php

// app/autoload.php
$loader->registerNamespaces(array(
    // ...
    'Rollerworks'              => __DIR__.'/../vendor/bundles',
    // ...
));
```

Now use the ``vendors`` script to clone the newly added repositories
into your project:

```bash
$ php bin/vendors install
```

### Step 1 (alternative): Using submodules (Symfony 2.0.x)

If you're managing your vendor libraries with submodules, first create the
`vendor/bundles/Rollerworks/Bundle` directory:

``` bash
$ mkdir -pv vendor/bundles/Rollerworks/Bundle
```

Next, add the necessary submodule:

``` bash
$ git submodule add git://github.com/rollerscapes/RollerworksMailBundle.git vendor/bundles/Rollerworks/Bundle/MailBundle
```

### Step2: Configure the autoloader

Add the following entry to your autoloader:

``` php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
    // ...
    'Rollerworks'              => __DIR__.'/../vendor/bundles',
    // ...
));
```

### Step3: Enable the bundle

Finally, enable the bundle in the kernel:

``` php
<?php

// in AppKernel::registerBundles()
$bundles = array(
    // ...
    new Rollerworks\Bundle\MailBundle\RollerworksMailBundle(),
    // ...
);
```

Congratulations! You're ready!

## Basic Usage (Template decorator)

___See http://swiftmailer.org/docs/plugins.html#decorator-plugin for more in-dept details.___

When creating an new e-mail message add the following.

``` php
<?php

// Replacements must be an array or implementation of \Swift_Plugins_Decorator_Replacements
// Each key is an e-mailadres and the value an array that is directly passed to render() of the templating engine.

$replacements = array(
    "address1@domain.tld" => array("a" => "b", "c" => "d"),
    "address2@domain.tld" => array("a" => "x", "c" => "y")
);

// Template filename follows the Symfony template resolving convention ([Bundle]:[Dir]:[filename].[type].[ext]).
$templates = array(
    'html' => 'AcmeHelloBundle:Email:Order.html.twig',
    'text' => 'AcmeHelloBundle:Email:Order.txt.twig'
);

$templating = $container->get('templating');

$decorator = new \Rollerworks\Bundle\MailBundle\Decorator\TemplateDecorator($templating, $replacements, $templates);
$mailer->registerPlugin($decorator);
```

## Basic Usage (Attachment decorator)

___This can be used in combination with the Template decorator.___

Also see:

* http://swiftmailer.org/docs/plugins.html#decorator-plugin
* http://swiftmailer.org/docs/messages.html#attaching-files

For more in-dept detail.

When creating an new e-mail message add the following.

Replacements must be an array or implementation of \Swift_Plugins_Decorator_Replacements
Each key is an e-mailadres and the value an array with attachments.
An attachment is either a \Swift_Attachment object or an array with the following keys and data:

``` php
 array('data'     => 'raw-file-content',
       'filename' => 'some-file.txt',
       'type'     => 'optional mime-type')
```

***Note: data must not be base64 encoded but provided as-is.***

``` php
<?php

$replacements = array(
    "address1@domain.tld" => array(new \Swift_Attachment::fromPath('/path/to/image.jpg', 'image/jpeg')),
    "address2@domain.tld" => array(array('data' => 'Please read me...', 'filename' => 'agreement.txt', 'type' => 'text/plain'))
);

$decorator = new \Rollerworks\Bundle\MailBundle\Decorator\TemplateDecorator($replacements);
$mailer->registerPlugin($decorator);
```


