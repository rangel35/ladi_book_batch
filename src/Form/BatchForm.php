<?php

namespace Drupal\ladi_book_batch\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;

use Drupal\Core\Controller\ControllerBase;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ladi_book_batch\BatchEntry;

/**
* Implements batch form.
*/
class BatchForm extends FormBase {

/**
* {@inheritdoc}
*/
  public function getFormId() {
    return 'ladi_batch_form';
  }

/**
* {@inheritdoc}
*/
  public function buildForm(array $form, FormStateInterface $form_state) {

    $user = User::load(\Drupal::currentUser()->id());
	$email = $user->get('mail')->value;
	$name = $user->get('name')->value;
	$userID = \Drupal::currentUser()->id();

    $form['enteredBy'] = array(
        '#type' => 'hidden',
        '#default_value' => $name,
        '#required' => TRUE,
    );
    $form['userEmail'] = array(
        '#type' => 'hidden',
        '#default_value' => $email,
        '#required' => TRUE,
    );
    $form['userID'] = array(
        '#type' => 'hidden',
        '#default_value' => $userID,
        '#required' => TRUE,
    );
    $form['namespace'] = array(
		'#type' => 'textfield',
		'#title' => t('Namespace'),
		'#description' => t('preserve namespaces if desired will show up in uri along with uuid'),
    );
  
    $form['collection'] = array(
        '#type' => 'textfield',
        '#title' => t('Enter URI of Collection that will contain your batch of assets.'),
        '#default_value' => '',
        '#description' => t('TIP: navigate to that Collection, and copy/paste URL (example: )'),
    );

    $form['location'] = array(
        '#type' => 'textfield',
        '#title' => t('Enter name of directory on the FTP server that contains your assets ([your directory name here])'),
        '#default_value' => '',
        '#description' => t('Enter your directory name only (EXAMPLE: directory_name)'),
        '#required' => TRUE,
    );
 	$form['batchType'] = array(
		'#type' => 'radios',
		'#title' => t('Ingest Type'),
		'#options' => array(
			t('Books with pages'),
			t('Individual Items (single or multi)'),
		),
		'#required' => TRUE,
	);


    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];
    return $form;
  }


/**
* {@inheritdoc}
*/
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $row = array();
        // Get the field
        $row['namespace'] = $form_state->getValue('namespace');
        $row['userEmail'] = $form_state->getValue('userEmail');
        $row['userID'] = $form_state->getValue('userID');
        $row['userName'] = $form_state->getValue('enteredBy');
        $row['collection'] = $form_state->getValue('collection');
        $row['location'] = $form_state->getValue('location');
        $row['batchType'] = $form_state->getValue('batchType');

        $row['batchID'] = $row['userName'] . time();
        $row['status'] = 0;
        $input_dir = '/staging/Assets';
        
        $batchrow = new BatchEntry($row, $input_dir);
        
        $batchrow->format_batch_info($row) ;
        
        $batchrow->add_batchrow_to_batch_queue($row['batchID'], $row['namespace'], $row['collection'], $row['location'], $row['userID'], $row['userEmail'], $row['userName'], $row['batchType']);

    }

}


