<?php

namespace Drupal\ladi_book_batch\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a custom Block for LADI book navigation.
 *
 * @Block(
 *   id = "ladi_book_nav_block",
 * )
 */

class LadiBookNavBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
    
  public function build() {
    
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
        $pg_id = $node->id();
        $parent_id = $node->book['bid'];
    }
          
    if (($parent_id === false) || ($parent_id === NULL)) {
        return;
    }
    
    $connection = \Drupal\Core\Database\Database::getConnection();
    $resultN = $connection->query("SELECT nid FROM book WHERE bid = :bid", [
        ':bid' => $parent_id,
    ]);
    
    $prevNid = 0;
    $doneNav = 'no';
      
    if ($resultN) {
        while ($rec = $resultN->fetchAssoc()) {
            $BPnode = node_load($rec['nid']);
            
            if ($doneNav == 'yes') {
                $nextNid = $BPnode->id();
                break;
            } else {
                $currNid = $BPnode->id();
            }
            
            if ($currNid == $pg_id) {
                $doneNav = 'yes';
                continue;
            } else {
                $prevNid = $currNid;
            }
            
        }
    }
      
    return \Drupal::formBuilder()->getForm('Drupal\ladi_book_batch\Form\BookNavForm');
  }

   public function getCacheMaxAge() {
    return 0;
   }
  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }
    
  /**
   * {@inheritdoc}
   */
   public function blockForm($form, FormStateInterface $form_state) {

    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['ladi_book_nav_block_settings'] = $form_state->getValue('ladi_book_nav_block_settings');
  }

}