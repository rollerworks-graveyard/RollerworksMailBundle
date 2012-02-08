[RollerworksMailBundle](http://projects.rollerscapes.net/RollerFramework/)
==================================================

This bundle provides the RollerworksMailBundle, providing Templating and AttachementDecorating for SwiftMailer 

### Template

SwiftMailer template decorator.
Handles e-mail messages using the Templating engine.

### AttachmentDecorator

SwiftMailer attachment decorator.
Similar to Mail:Template, but instead it handles attachments.

## Installation

Installation depends on how your project is setup:

### Step 1: Installation using the `bin/vendors.php` method

If you're using the `bin/vendors.php` method to manage your vendor libraries,
add the following entry to the `deps` in the root of your project file:

```
[RollerworksMailBundle]
    git=https://github.com/Rollerscapes/RollerworksMailBundle.git
    target=/vendor/bundles/Rollerworks/MailBundle
```

Next, update your vendors by running:

```bash
$ ./bin/vendors
```

Great! Now skip down to *Step 2*.

### Step 1 (alternative): Installation with sub-modules

If you're managing your vendor libraries with sub-modules, first create the
`vendor/bundles/Rollerworks/MailBundle` directory:

```bash
$ mkdir -pv vendor/bundles/Rollerworks/MailBundle
```

Next, add the necessary sub-module:

```bash
$ git submodule add https://github.com/Rollerscapes/RollerworksMailBundle.git vendor/bundles/Rollerworks/MailBundle
```

### Step2: Configure the autoloader

Add the following entry to your autoloader:

```php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
    // ...
    'Rollerworks' => __DIR__.'/../vendor/bundles',
));
```

### Step3: Enable the bundle

Finally, enable the bundle in the kernel:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Rollerworks\MailBundle\RollerworksMailBundle(),
    );
}
```

### Step4: Configure the bundle

Nothing needs to be configured, just use the decorators when creating an new e-mail message.