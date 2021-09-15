<?php

namespace Drupal\ladi_book_batch\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation;
use Drupal\ladi_book_batch\Email;
use Drupal\Core\Controller\ControllerBase;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ladi_book_batch\BatchEntry;

/**
* Implements batch form.
*/
class BookNavForm extends FormBase
{
    use LoggerChannelTrait;
    /**
    * {@inheritdoc}
    */
    public function __construct()
    {
        $this->email = new Email();
    }

    public function getFormId()
    {
        return 'ladi_book_nav_block_form';
    }

    /**
    * {@inheritdoc}
    */

    public function buildForm(array $form, FormStateInterface $form_state, $extra = null)
    {
        try {
            $node = \Drupal::routeMatch()->getParameter('node');
            if ($node instanceof \Drupal\node\NodeInterface) {
                $nid = $node->id();
                $pgTitle = $node->getTitle();
                $parent_id = $node->book['bid'];
                $parent_node = node_load($parent_id);
                $parent_title = $parent_node->label();
                $contentType = $node->bundle() ;
            }
        
            $extra['currPg'] = $nid;
            $extra['parent'] = $parent_id;
            $extra['parentTitle'] = $parent_title;
            $extra['title'] = $pgTitle;
            $extra['contentType'] = $contentType;

            $form['pages'] = array(
            '#type' => 'select',
            '#title' => t('JumpTo'),
            '#options' => $this->custom_function_for_options($extra),
            '#default_value' => $nid,
            '#required' => true,
        );
    

            $form['actions']['#type'] = 'actions';
            $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#button_type' => 'primary',
        ];
            return $form;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("BookNavForm build error: {$msg}");
            $this->email->send_error_email("BookNavForm build error: {$msg}");
        }
    }


    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        try {
            $page2jump = $form_state->getValue('pages') ;
            $newpg = node_load($page2jump);
            $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $newpg->id()]);
            return $form_state->setRedirectUrl($url);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("BookNavForm submit error: {$msg}");
            $this->email->send_error_email("BookNavForm submit error: {$msg}");
        }
    }

    public function custom_function_for_options($extra)
    {
        try {
            $bookPg = array();
            $BPnode = node_load($extra['currPg']) ;
            $nid = $extra['currPg'] ;
            $parent_id = $extra['parent'] ;
            $parent_title = $extra['parentTitle'];
            $contentType = $extra['contentType'] ;
            $connection = \Drupal\Core\Database\Database::getConnection();
            
            $ladi = false;
            if ($contentType == 'ladi_content') {
                $ladi = true;
            }

            if ($ladi && $nid === $parent_id) {
                $bid = $nid;
            } else {
                $bid = $parent_id;
            }
            
            $resultB = $connection->query("SELECT nid FROM book WHERE bid = :bid", [
                ':bid' => $bid,
            ]);

            if ($resultB) {
                while ($rec = $resultB->fetchAssoc()) {
                    $BPnode = node_load($rec['nid']);
                    $BP_title = $BPnode->label() ;
                    $pgNum = str_replace($parent_title, '', $BP_title) ;
                    $pgNum = str_replace("-", '', $pgNum) ;
                    $pgNum = trim($pgNum) ;
                    if(strlen($pgNum > 0)){
                        $bookPg[$rec['nid']] = "Page " . $pgNum ;
                    }
                    else{
                        $bookPg[$rec['nid']] = $BP_title ;
                    }
                }
            }

            //var_dump($bookPg) ;
            $options = array();
            foreach ($bookPg as $k => $v) {
                $options[$k] = $v ;
            }
            return $options;
        } 
        catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("BookNavForm options function error: {$msg}");
            $this->email->send_error_email("BookNavForm options function error: {$msg}");
        }
    }
}
