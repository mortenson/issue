# Drupal issue queue commands

Contains commands useful for contributing to core issues.

# Requirements

1. Unix environment
1. [git](https://git-scm.com/downloads)
1. [composer](https://getcomposer.org/)
1. create-patch only: [interdiff](http://freshmeat.sourceforge.net/projects/patchutils)
1. test only: [phantomjs](http://phantomjs.org/download.html) and [chromedriver](https://sites.google.com/a/chromium.org/chromedriver/downloads)
1. A Drupal codebase checked out with Git

# Installation

```bash
git clone https://git.drupal.org/project/drupal.git
cd drupal
composer install
git clone git@github.com:mortenson/issue.git
```

Commands can then be run using `php issue/command`.

# Commands

## patch

Patches a Drupal project based on an issue number.

```bash
~/repos/drupal (8.6.x)$ php issue/command patch

 What issue are you working on?:
 > 2962525

 What patch would you like to apply?:
  [0] 2962525-combined-12.patch
  [1] 2962525-combined-16.patch
  [2] 2962525-combined-17.patch
  [3] 2962525-17.patch
  [4] 2962525-combined-18.patch
  [5] 2962525-combined-21.patch
  [6] 2962525-21.patch
 > 5

Successfully applied 2962525-combined-21.patch
```

## create-patch

Creates a patch and interdiff based on an issue number.

```bash
~/repos/drupal (8.6.x *)$ php issue/command create-patch 2962110
Created 2962110-44.patch

 What patch do you want to create an interdiff from?:
  [0] 2962110-42.patch
  [1] Do not create interdiff
 > 0

Created interdiff-2962110-42-44.txt
```

## test

Performs tests based on changed files in a project.

```bash
~/repos/drupal (8.6.x *+)$ php -S 127.0.0.1:12345 .ht.router.php > /dev/null 2>&1 &
[1] 41032
~/repos/drupal (8.6.x *+)$ php issue/command test drupal --url=http://127.0.0.1:12345

 What test would you like to run?:
  [0] core/modules/big_pipe/tests/src/Functional/BigPipeTest.php
  [1] core/modules/big_pipe/tests/src/FunctionalJavascript/BigPipeRegressionTest.php
 > 0

PHPUnit 6.5.8 by Sebastian Bergmann and contributors.

Testing Drupal\Tests\big_pipe\Functional\BigPipeTest
....                                                                4 / 4 (100%)

Time: 21.8 seconds, Memory: 4.00MB

OK (4 tests, 177 assertions)
```

# Alternatives

If you prefer tracking changes with Git instead of using patch files, you
should check out [dorgflow](https://github.com/joachim-n/dorgflow).
