# Drupal issue queue commands

Contains commands useful for contributing to core issues.

# Installation

```bash
git clone https://git.drupal.org/project/drupal.git
cd drupal
composer install
git clone git@github.com:mortenson/issue.git
php issue/command patch
# Follow prompts...
php core/scripts/drupal quick-start
```

# Example usage

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
~/repos/drupal (8.6.x *)$ php core/scripts/drupal quick-start

 Select an installation profile [Install with commonly used features pre-configured.]:
  [standard  ] Install with commonly used features pre-configured.
  [minimal   ] Build a custom site without pre-configured functionality. Suitable for advanced users.
  [demo_umami] Install an example site that shows off some of Drupal's capabilities.
 > standard

18/18 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
Congratulations, you installed Drupal!
Username: admin
Password: **********
Drupal development server started: <http://127.0.0.1:8888>
This server is not meant for production use.
One time login url: <http://127.0.0.1:8888/user/reset/1/1526083016/nk4ohwos3-ejhFy3ht6JIWl1CWFKW-RdWn6ydVi670k/login>
Press Ctrl-C to quit the Drupal development server.
```
