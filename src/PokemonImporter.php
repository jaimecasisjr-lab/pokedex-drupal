<?php

namespace Drupal\pokemon_harvest;

use PokePHP\PokeApi;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\taxonomy\Entity\Term; // <--- Crucial for creating terms

class PokemonImporter {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileRepositoryInterface $fileRepository
  ) {}

  public function harvestGen1() {
    $api = new PokeApi();
    $count = 0;

    for ($i = 1; $i <= 151; $i++) {
      $response = $api->pokemon($i);
      $data = json_decode($response, TRUE);
      if (!$data) continue;

      $storage = $this->entityTypeManager->getStorage('node');
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

      // 1. Process Types (Taxonomy)
      $types_ids = [];
      foreach ($data['types'] as $type_info) {
        $type_name = ucfirst($type_info['type']['name']);
        
        // Look for existing term in 'pokemon_types' vocabulary
        $existing_terms = $term_storage->loadByProperties([
          'name' => $type_name,
          'vid' => 'pokemon_types',
        ]);

        if (empty($existing_terms)) {
          $term = Term::create([
            'name' => $type_name,
            'vid' => 'pokemon_types',
          ]);
          $term->save();
          $types_ids[] = $term->id();
        } else {
          $term = reset($existing_terms);
          $types_ids[] = $term->id();
        }
      }

      // 2. Process Sprite Image
      $file_id = NULL;
      $sprite_url = $data['sprites']['front_default'];
      if ($sprite_url) {
        $image_data = @file_get_contents($sprite_url);
        if ($image_data) {
          $filename = 'pokemon-' . $data['id'] . '.png';
          $file = $this->fileRepository->writeData(
            $image_data, 
            "public://$filename", 
            FileSystemInterface::EXISTS_REPLACE
          );
          $file_id = $file->id();
        }
      }

      // 3. Create or Update Node
      $existing_nodes = $storage->loadByProperties([
        'type' => 'pokedex',
        'field_id' => $data['id'],
      ]);

      $node_values = [
        'type' => 'pokedex',
        'title' => ucfirst($data['name']),
        'field_id' => $data['id'],
        'field_height' => $data['height'],
        'field_sprite' => [
          'target_id' => $file_id,
          'alt' => $data['name'],
        ],
        'field_pokemon_types' => $types_ids, // <--- Linking the terms here
        'status' => 1,
      ];

      if (empty($existing_nodes)) {
        $node = $storage->create($node_values);
      } else {
        $node = reset($existing_nodes);
        foreach ($node_values as $key => $value) {
          if ($key !== 'type') {
            $node->set($key, $value);
          }
        }
      }
      
      $node->save();
      $count++;
    }
    return $count;
  }
}