<?php

namespace Drupal\pokemon_harvest;

use PokePHP\PokeApi;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;

class PokemonImporter {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileRepositoryInterface $fileRepository
  ) {}

  public function harvestGen1() {
    $api = new PokeApi();
    $count = 0;

    //Intialize the storage not before loop
    $storage = $this->entityTypeManager->getStorage('node');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    for ($i = 1; $i <= 151; $i++) {
      $response = $api->pokemon($i);
      $data = json_decode($response, TRUE);
      if (!$data) continue;

      // 1. Process Taxonomy Terms (Fetching the IDs)
      $tids = [];
      if (!empty($data['types'])) {
        foreach ($data['types'] as $type_item) {
          $api_type_name = ucfirst($type_item['type']['name']);
          $matching_terms = $term_storage->loadByProperties([
            'vid' => 'pokemon_types',
            'name' => $api_type_name,
          ]);

          if (!empty($matching_terms)) {
            $term = reset($matching_terms);


            $tids[] = ['target_id' => $term->id()];
          }
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
      // 3. Prepare Node Values
      $node_values = [
        'type' => 'pokedex',
        'title' => ucfirst($data['name']),
        'field_id' => (int)$data['id'],
        'field_height' => $data['height'],
        'field_pokemon_type' => $tids, //this line assign the fetched taxonomy term IDs
        'field_sprite' => [
          'target_id' => $file_id,
          'alt' => $data['name'],
        ],
      ];

      // 4. Create or Update Node
      $existing_nodes = $storage->loadByProperties([
        'type' => 'pokedex',
        'field_id' => $data['id'],
      ]);

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