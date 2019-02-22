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
			t('Single book with pages'),
			t('Multi-Books with pages'),
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
    
	// Get the field
	$nsp = $form_state->getValue('namespace');
	$userEmail = $form_state->getValue('userEmail');
	$userID = $form_state->getValue('userID');
    $staff = $form_state->getValue('enteredBy');
    $collection = $form_state->getValue('collection');
	$location = $form_state->getValue('location');
    $batchType = $form_state->getValue('batchType');
    
    $batchID = $staff . time();

	drupal_set_message(t(\ingest\BatchEntry::format_batch_submission_output($batchID,$collection,$location, $staff,$userEmail, $batchType)), 'status');
	\ingest\BatchEntry::add_batchrow_to_batch_queue($batchID,$collection,$location, $userID,$userEmail, $batchType);

  }

}
