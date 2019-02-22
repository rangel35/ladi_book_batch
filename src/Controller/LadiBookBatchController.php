<?php
/**
 * @file
 * Contains \Drupal\ladi_book_batch\Controller\LadiBookBatchController.
 */
 
namespace Drupal\ladi_book_batch\Controller;
 
use Drupal\Core\Controller\ControllerBase;
 
class LadiBookBatchController extends ControllerBase {
  public function content() {
	return array(
	  '#type' => 'markup',
	  '#markup' => t('Hello LADI Batch'),
	);
  }
}
