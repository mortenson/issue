<?php

namespace DrupalIssue;

use Drupal\Core\Extension\ExtensionDiscovery as DrupalExtensionDiscovery;

class ExtensionDiscovery extends DrupalExtensionDiscovery {

  public function clearCache() {
    self::$files = [];
  }

}
