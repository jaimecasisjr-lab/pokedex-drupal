<?php

namespace Drupal\pokemon_harvest\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManagerInterface;

    /**
     * A Drush commandfile for pokemon harvest.
     */
class TypeImporter extends DrushCommands {
    /**
     * The entity type manager.
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * Construct a new PokemonCommands object.
     */
    public function __construct(EntityTypeManagerInterface $entity_type_manager) {  
            parent:: __construct();
            $this->entityTypeManager = $entity_type_manager;
        }

    /**
     * Imports Pokemon types into a custom vocabulary
     * 
     * @command pokemon:import-types
     * @aliases p-imp
     * @usage drush pokemon:import types
     */
    public function importTypes() {
        $vid = 'pokemon_types';
        $v_name = 'Pokemon Types';

        // initialize the vocabulary, check if exists
        $vocabulary = Vocabulary::load($vid);
        //execute the statement
        if (!$vocabulary) {
            $vocabulary = Vocabulary::create([
                'vid' => $vid,
                'name' =>$v_name,
                'description' => 'Pokemon type'
                ]);
        $vocabulary->save();
        $this->output->writeln("created the vocabulary: {$v_name}");
    }
    //create now the term
    //initialize
    $terms_to_create = [
        'Bug', 'Dark', 'Dragon', 'Electric', 'Fairy', 'Fighting', 
      'Fire', 'Flying', 'Ghost', 'Ice', 'Normal', 'Poison', 
      'Psychic', 'Rock', 'Steel', 'Water', 'Grass',
    ];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

        foreach ($terms_to_create as $term_name) {
            $existing = $storage->loadByProperties([
                'name' => $term_name,
                'vid' => $vid,
            ]);

            if (empty($existing)) {
                $term = Term::create ([
                'vid' =>$vid,
                'name' => $term_name,
                ]);
            $term->save();
         //   $this->logger()->success(dt('Created term: @name', ['@name'=>$term_name]));
         //   }else{
         //   $this->logger()->info(dt('Skipped existing term: @name', ['@name'=>$term_name]));

            }
        }

        $this->output()->writeln('Pokemon type import complete.');


}
}
