<?php

namespace Drupal\ladi_book_batch\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Language\LanguageInterface;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "ladi_content_block",
 *   admin_label = @Translation("Ladi Content Block"),
 * )
 */
class LadiContentBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $taxonomy_fields = array('field_genre' => 'categories', 'field_physical_location' => 'country_of_origin', 'field_dcterms_language' => 'language', 'field_geographic_city' => 'subject_geographic_cities', 'field_geographic_city_section' => 'subject_geographic_city_sections', 'field_geographic_county' => 'subject_geographic_counties', 'field_geographic_country' => 'subject_geographic_countries', 'field_geographic_department' => 'subject_geographic_departments', 'field_geographic_municipality' => 'subject_geographic_municipalities', 'field_geographic_province' => 'subject_geographic_provinces', 'field_geographic_region' => 'subject_geographic_regions', 'field_geographic_state' => 'subject_geographic_states', 'field_foaf_organization' => 'subject_groups', 'field_foaf_person' => 'subject_people', 'field_subject_topic' => 'subjects', 'field_resource_type' => 'resource_type') ;
    
    $collection_fields = array('field_part_of', 'field_member_of', 'field_repository') ;
    
    $ladicontent_en = array(
        'title' => 'Title:', 'uuid' => 'UUID:', 'field_dcterms_alternative' => 'Titles - alternative:', 'field_date_creation' => 'Date - creation:', 'field_date_other' => 'Date - other:', 'field_description' => 'Description:', 'field_identifier_local' => 'Digital identifier:', 'field_physical_location' => 'Country of origin:', 'field_repository' => 'Physical repository:', 'field_member_of' => 'Partner Collection:', 'field_extent' => 'Extent:', 'field_access_condition' => 'Access Rights:', 'field_condition' => 'Condition of material:', 'field_table_contents' => 'Table of contents:', 'field_number' => 'Number:', 'field_notes' => 'Notes:', 'field_publisher' => 'Publishers:', 'field_publication_location' => 'Place of publication:', 'field_digital_origin' => 'Digitization details:', 'field_production_location' => 'Location of production:', 'field_origin_info' => 'Origin information:', 'field_rights_statement' => 'Rights Statement:', 'field_resource_type' => 'Type of resource:', 'field_volume' => 'Volume:', 'field_linked_agent' => 'Contributors:', 'field_inscription' => 'Inscription:', 'field_genre' => 'Categories:', 'field_subject_topic' => 'Subjects:', 'field_subject_geographic' => 'Subject - Geographic coverage:', 'field_geographic_city' => 'Subject - geographic cities:', 'field_geographic_city_section' => 'Subject - geographic city sections:', 'field_geographic_county' => 'Subject - geographic counties:', 'field_geographic_country' => 'Subject - geographic countries:', 'field_geographic_department' => 'Subject - geographic departments:', 'field_geographic_municipality' => 'Subject - geographic municipalities:', 'field_geographic_province' => 'Subject - geographic provinces:', 'field_geographic_region' => 'Subject - geographic regions:', 'field_geographic_state' => 'Subject - geographic states:', 'field_foaf_organization' => 'Subject - groups:', 'field_foaf_person' => 'Subject - people:', 'field_part_of' => 'Part of:', 'field_dcterms_language' => 'Languages:') ; 
      
    $ladicontent_es = array( 
        'title' => 'Título:', 'uuid' => 'UUID:', 'field_dcterms_alternative' => 'Título alternativo:', 'field_date_creation' => 'Fecha de creación:', 'field_date_other' => 'Otras fechas:', 'field_description' => 'Descripción:', 'field_identifier_local' => 'Identificador digital:', 'field_physical_location' => 'País de origen:', 'field_repository' => 'Repositorio físico:', 'field_member_of' => 'Colección de colaborador:', 'field_extent' => 'Tamaño:', 'field_access_condition' => 'Derechos de acceso:', 'field_condition' => 'Condiciones Fisicas:', 'field_table_contents' => 'Tabla de contenidos:', 'field_number' => 'Numero:', 'field_notes' => 'Notas:', 'field_publisher' => 'Editorial:', 'field_publication_location' => 'Lugar de publicación:', 'field_digital_origin' => 'detalles de digitalización:', 'field_production_location' => 'Location of production:', 'field_origin_info' => 'Información de Origen:', 'field_rights_statement' => 'Titulares de los derechos:', 'field_resource_type' => 'Tipo de recurso:', 'field_volume' => 'Tomo:', 'field_linked_agent' => 'Contribuyentes:', 'field_inscription' => 'Inscripción:', 'field_genre' => 'Categorías:', 'field_subject_topic' => 'Materia:', 'field_subject_geographic' => 'Materia - cobertura geografica:', 'field_geographic_city' => 'Materia - cobertura de cuidad:', 'field_geographic_city_section' => 'Materia - cobertura de sección de la ciudad:', 'field_geographic_county' => 'Materia - cobertura de condado:', 'field_geographic_country' => 'Materia - cobertura de país:', 'field_geographic_department' => 'Materia - cobertura de departamento:', 'field_geographic_municipality' => 'Subject - geographic municipalities:', 'field_geographic_province' => 'Materia - cobertura de provincia:', 'field_geographic_region' => 'Materia - cobertura de región:', 'field_geographic_state' => 'Materia - cobertura do estados:', 'field_foaf_organization' => 'Materia - grupos:', 'field_foaf_person' => 'Materia - personas:', 'field_part_of' => 'Part of:', 'field_dcterms_language' => 'Idiomas:') ;    
    
    $ladicontent_pt_br = array( 
        'title' => 'Título:', 'uuid' => 'UUID:', 'field_dcterms_alternative' => 'Título alternativo:', 'field_date_creation' => 'Data de criação:', 'field_date_other' => 'Outras datas:', 'field_description' => 'Descrição:', 'field_identifier_local' => 'Identificação digital:', 'field_physical_location' => 'País de origem:', 'field_repository' => 'Repositório físico:', 'field_member_of' => 'Coleção de colaboradora:', 'field_extent' => 'Extensão :', 'field_access_condition' => 'Direitos de acesso:', 'field_condition' => 'Condição física:', 'field_table_contents' => 'Índice:', 'field_number' => 'Número:', 'field_notes' => 'Notas:', 'field_publisher' => 'Editor:', 'field_publication_location' => 'Local de publicação:', 'field_digital_origin' => 'Detalhes de Digitalização:', 'field_production_location' => 'Location of production:', 'field_origin_info' => 'Informações origen:', 'field_rights_statement' => 'Declaração de direitos:', 'field_resource_type' => 'Tipo de Recurso:', 'field_volume' => 'Tomo:', 'field_linked_agent' => 'Contribuintes:', 'field_inscription' => 'Inscrição :', 'field_genre' => 'Categorias:', 'field_subject_topic' => 'Assunto:', 'field_subject_geographic' => 'Assunto - cobertura geográfica:', 'field_geographic_city' => 'Assunto - cobertura da cidade:', 'field_geographic_city_section' => 'Assunto - cobertura da seção da cidade:', 'field_geographic_county' => 'Assunto - cobertura do condado:', 'field_geographic_country' => 'Assunto - cobertura do país:', 'field_geographic_department' => 'Assunto - cobertura do departamento:', 'field_geographic_municipality' => 'Subject - geographic municipalities:', 'field_geographic_province' => 'Assunto - cobertura da província:', 'field_geographic_region' => 'Assunto - cobertura da região:', 'field_geographic_state' => 'Assunto - cobertura de estados:', 'field_foaf_organization' => 'Assunto - grupos:', 'field_foaf_person' => 'Assunto - pessoas:', 'field_part_of' => 'Part of:', 'field_dcterms_language' => 'Idiomas:') ; 
      
    $rels_en = array(
        'rel:art' => 'Artist', 'rel:aut' => 'Author', 'rel:cre' => 'Creator', 'rel:crp' => 'Correspondant', 'rel:ctb' => 'Contributor', 'rel:dfd' => 'Accused ', 'rel:edt' => 'Editor', 'rel:ill' => 'Illustrator', 'rel:ivr' => 'Interviewer', 'rel:jud' => 'Judge', 'rel:led' => 'Director', 'rel:mtk' => 'Rapporteur', 'rel:pbl' => 'Publisher', 'rel:pdr' => 'Administrador', 'rel:pht' => 'Photographer', 'rel:prt' => 'Printer', 'rel:ptf' => 'Plaintiff', 'rel:res' => 'Researcher', 'rel:rpt' => 'Reporter', 'rel:scr' => 'Scribe', 'rel:sec' => 'Secretary', 'rel:trl' => 'Interpreter', 'rel:wit' => 'Witness', 'rel:ctr' => 'Contractor', 'rel:dnr' => 'Donor');
          
    $rels_es = array(
        'rel:art' => 'Artista', 'rel:aut' => 'Autora', 'rel:cre' => 'Creadora', 'rel:crp' => 'Enviado Especial', 'rel:ctb' => 'Contribuyente', 'rel:dfd' => 'Acusada', 'rel:edt' => 'Redactor', 'rel:ill' => 'Ilustrador', 'rel:ivr' => 'Entrevistador', 'rel:jud' => 'Juez', 'rel:led' => 'Directora', 'rel:mtk' => 'Relator', 'rel:pbl' => 'Editorial', 'rel:pdr' => 'Administradora', 'rel:pht' => 'Fotógrafo', 'rel:prt' => 'Impresor', 'rel:ptf' => 'Demandante', 'rel:res' => 'Investigadora', 'rel:rpt' => 'Reportera', 'rel:scr' => 'Escribano', 'rel:sec' => 'Secretaría', 'rel:trl' => 'Intérprete', 'rel:wit' => 'Testigo', 'rel:ctr' => 'Contratista', 'rel:dnr' => 'Donante');

    $rels_pt_br = array(
        'rel:art' => 'Artista', 'rel:aut' => 'Autora', 'rel:cre' => 'Criadora', 'rel:crp' => 'Correspodente', 'rel:ctb' => 'Contribuidor', 'rel:dfd' => 'Acusada', 'rel:edt' => 'Editora', 'rel:ill' => 'Ilustradora', 'rel:ivr' => 'Entrevistador', 'rel:jud' => 'Juíza', 'rel:led' => 'Diretora', 'rel:mtk' => 'Relator', 'rel:pbl' => 'Editora', 'rel:pdr' => 'Administradora', 'rel:pht' => 'Fotógrafa', 'rel:prt' => 'Impressora', 'rel:ptf' => 'Demandante', 'rel:res' => 'Pesquisadora', 'rel:rpt' => 'Repórter', 'rel:scr' => 'Escrivão', 'rel:sec' => 'Secretariado', 'rel:trl' => 'Intérprete', 'rel:wit' => 'Testemunha', 'rel:ctr' => 'Contratante', 'rel:dnr' => 'Doador');
      
    $accessRights['en'] = "This electronic resource is made available by the University of Texas Libraries solely for the purposes of research, teaching and private study. Formal permission to reuse or republish this content must be obtained from the copyright holder.";
    $accessRights['es'] = "University of Texas Libraries provee accesso a este material electrónico solamente para la investigación y la enseñanza. Es necesario pedir permiso del/la autor/a para usar o publicarlo.";
    $accessRights['pt-br'] = "Este recurso eletrônico é disponibilizado pela University of Texas Libraries para finalidades exclusivas de pesquisa, ensino e estudo privado. Para obter permissão formal para reutilizar ou republicar este conteúdo, procure o detentor do direito autoral.";

    $rightsStmt['en'] = "All intellectual property rights are retained by the legal copyright holders. The University of Texas does not hold the copyright to the content of this file." ;
    $rightsStmt['es'] = "Todos los derechos de propriedad intelectual pertenece al/la autor/a legal. University of Texas Libraries no tiene los derechos de propriedad intelectual para este material." ;
    $rightsStmt['pt-br'] = "Todos os direitos autorais (copyrights) pertencem a seus titulares legais. A University of Texas não é proprietária de nenhum direito autoral relativo ao conteúdo deste arquivo." ;
    
    $all_fields = array();
      
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
        $pg_id = $node->id();
      
        $all_fields['title'] = $node->label();
        $all_fields['uuid'] = $node->uuid();
        $all_fields['field_dcterms_alternative'] = $node->get('field_dcterms_alternative')->getString() ; 
        $all_fields['field_date_creation'] = $node->get('field_date_creation')->getString() ; 
        $all_fields['field_date_other'] = $node->get('field_date_other')->getString() ; 
        $all_fields['field_description'] = $node->get('field_description')->getString() ; 
        $all_fields['field_identifier_local'] = $node->get('field_identifier_local')->getString() ; 
        $all_fields['field_physical_location'] = $node->get('field_physical_location')->getString() ; 
        $all_fields['field_repository'] = $node->get('field_repository')->getString() ; 
        $all_fields['field_member_of'] = $node->get('field_member_of')->getString() ; 
        $all_fields['field_extent'] = $node->get('field_extent')->getString() ; 
        $all_fields['field_condition'] = $node->get('field_condition')->getString() ; 
        $all_fields['field_table_contents'] = $node->get('field_table_contents')->getString() ; 
        $all_fields['field_number'] = $node->get('field_number')->getString() ; 
        $all_fields['field_notes'] = $node->get('field_notes')->getString() ; 
        $all_fields['field_publisher'] = $node->get('field_publisher')->getString() ; 
        $all_fields['field_publication_location'] = $node->get('field_publication_location')->getString() ; 
        $all_fields['field_digital_origin'] = $node->get('field_digital_origin')->getString() ; 
        $all_fields['field_production_location'] = $node->get('field_production_location')->getString() ; 
        $all_fields['field_origin_info'] = $node->get('field_origin_info')->getString() ; 
        $all_fields['field_resource_type'] = $node->get('field_resource_type')->getString() ; 
        $all_fields['field_volume'] = $node->get('field_volume')->getString() ; 
        $all_fields['field_linked_agent'] = $node->get('field_linked_agent')->getString() ; 
        $all_fields['field_inscription'] = $node->get('field_inscription')->getString() ; 
        $all_fields['field_genre'] = $node->get('field_genre')->getString() ; 
        $all_fields['field_subject_topic'] = $node->get('field_subject_topic')->getString() ; 
        $all_fields['field_subject_geographic'] = $node->get('field_subject_geographic')->getString() ; 
        $all_fields['field_geographic_city'] = $node->get('field_geographic_city')->getString() ; 
        $all_fields['field_geographic_city_section'] = $node->get('field_geographic_city_section')->getString() ; 
        $all_fields['field_geographic_county'] = $node->get('field_geographic_county')->getString() ; 
        $all_fields['field_geographic_country'] = $node->get('field_geographic_country')->getString() ; 
        $all_fields['field_geographic_department'] = $node->get('field_geographic_department')->getString() ; 
        $all_fields['field_geographic_municipality'] = $node->get('field_geographic_municipality')->getString() ; 
        $all_fields['field_geographic_province'] = $node->get('field_geographic_province')->getString() ; 
        $all_fields['field_geographic_region'] = $node->get('field_geographic_region')->getString() ; 
        $all_fields['field_geographic_state'] = $node->get('field_geographic_state')->getString() ; 
        $all_fields['field_foaf_organization'] = $node->get('field_foaf_organization')->getString() ; 
        $all_fields['field_foaf_person'] = $node->get('field_foaf_person')->getString() ; 
        $all_fields['field_part_of'] = $node->get('field_part_of')->getString() ; 
        $all_fields['field_dcterms_language'] = $node->get('field_dcterms_language')->getString() ;     
        $all_fields['field_rights_statement'] = $node->get('field_rights_statement')->getString() ; 
        $all_fields['field_access_condition'] = $node->get('field_access_condition')->getString() ; 
    }
    
    $avail_fields = array();
    foreach ($all_fields as $key => $value)  {
        if (($key == "title") || ($key == "uuid")) {
            $avail_fields[$key] = $value;
            continue;
        }
        
        if (isset($value) && !empty($value)) {
            $language =  \Drupal::languageManager()->getCurrentLanguage()->getId();
            if (in_array($key, array_keys($taxonomy_fields))) {
                $taxArr = explode(", ", $value) ;
                $tmpVal = "" ;
                foreach($taxArr as $tA) {
                    
                    $term = \Drupal\taxonomy\Entity\Term::load($tA);
                    //$taxName = $term->getName();
                    
                    if($term->hasTranslation($language)){
                        $translated_term = \Drupal::service('entity.repository')->getTranslationFromContext($term, $language);
                        $tid = $term->id();
                        $taxName = $translated_term->getName();
                    } else {
                        $taxName = $term->getName();
                    }

                    $taxURL = "/" . $language . "/advanced-search?f[0]=" . $taxonomy_fields[$key] . ":" . $tA ;
                    $tmpVal .= '<a href="' . $taxURL . '">' . $taxName . '</a><br />' ;

                }
                $value = $tmpVal ;
            }

            if (in_array($key, $collection_fields)) {
                $entity_node = \Drupal::entityManager()->getStorage('node')->load($value);
                $entityName = $entity_node->label();
                $alias = \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$value);
                $value = '<a href="/' . $language . $alias . '">' . $entityName . '</a>' ;
            }
            
            if ($key == "field_linked_agent") {
                $agents = explode(", ", $value) ; 
                $tmpVal = "" ;
                if ($language == "es") {
                     $rels = $rels_es ;
                } elseif ($language == "pt-br") {
                    $rels = $rels_pt_br ;
                } else {
                    $rels = $rels_en ;
                }

                while (!empty($agents)) {
                    $agentID = array_shift($agents);
                    $agentRole = array_shift($agents);
                    
                    $term = \Drupal\taxonomy\Entity\Term::load($agentID);
                    $agentName = $term->getName();
                    
                    $agentURL = "/" . $language . '/advanced-search?field_linked_agent=%22' . $agentName . ' %28' . $agentID . '%29%22' ;
                    $tmpVal .= '(' . $rels[$agentRole] . ') ' . '<a href="' . $agentURL . '">' . $agentName . '</a><br />' ;

                }
                $value = $tmpVal ;
            }
            
            if ($key == "field_rights_statement") {
                $value= $rightsStmt[$language] ; 
            }
            if ($key == "field_access_condition") {
                $value= $accessRights[$language] ; 
            }
            
            $avail_fields[$key] = $value;
        }
    }
    
    $field_keys = array_keys($avail_fields);
    if ($language == "es") {
        $headingTitle="Detalles de los Metadatos" ;
        $ladicontent = $ladicontent_es ;
    } elseif ($language == "pt-br") {
        $headingTitle="Detalhes dos Metadados" ;
        $ladicontent = $ladicontent_pt_br ;
    } else {
        $headingTitle="Metadata Details" ;
        $ladicontent = $ladicontent_en ;
    }
      
    $content_display = '<div class="ladicontent-custom--block">' . "\n" . '<h2>' . $headingTitle . '</h2>' . "\n";
    foreach($field_keys as $k) {
        $content_display .= '<div class="md_row">' . "\n" . '<div class="md_label"><strong>' . $ladicontent[$k] . '</strong></div><div class="md_content">' . $avail_fields[$k] . '</div>' . "\n" . '</div>' . "\n" ;   
    }
    
    
    return [
      '#markup' => $this->t($content_display),

    ];
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
    $config = $this->getConfiguration();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['ladi_content_block_settings'] = $form_state->getValue('ladi_content_block_settings');
  }
}
