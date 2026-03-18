<?php

namespace Drupal\pokemon_harvest;

use PokePHP\PokeApi;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;

class PokemonImporter {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileRepositoryInterface $fileRepository,
    protected ClientInterface $httpClient
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
      // 2.5 Extract Stats
      $hp_value = $data['stats'][0]['base_stat'];     // HP is always index 0
      $attack_value = $data['stats'][1]['base_stat']; // Attack is always index 1

      $description = '';
      $prev_evo = ''; // Initialize variables
      $next_evo = '';
      try {
        $species_response = $this->httpClient->get("https://pokeapi.co/api/v2/pokemon-species/" . $data['id']);
        $species_data = json_decode($species_response->getBody(), TRUE);

        if (!empty($species_data['flavor_text_entries'])) {
          foreach ($species_data['flavor_text_entries'] as $entry) {
            if ($entry['language']['name'] == 'en') {
              $description = str_replace(["\n", "\f", "\r"], " ", $entry['flavor_text']);
              break; 
            }
          }
        }
        //Evolution LOGIC
        // 1. Get Pre-evolution
        if (!empty($species_data['evolves_from_species'])) {
          $prev_evo = ucfirst($species_data['evolves_from_species']['name']);
        }

        // 2. Get Next-evolution 
        if (!empty($species_data['evolution_chain']['url'])) {
          $evo_url = $species_data['evolution_chain']['url'];
          $evo_response = $this->httpClient->get($evo_url);
          $evo_data = json_decode($evo_response->getBody(), TRUE);

          $current_name = $data['name'];
          $chain = $evo_data['chain'];

          // Move through the chain until we find the current Pokemon
          while ($chain && $chain['species']['name'] !== $current_name) {
            $chain = $chain['evolves_to'][0] ?? null;
          }
          
          // If we found it and there is a next step, grab the name
          if ($chain && !empty($chain['evolves_to'])) {
            $next_evo = ucfirst($chain['evolves_to'][0]['species']['name']);
          }
        }
      } catch (\Exception $e) {
        // Handle potential API failure for specific species
        \Drupal::logger('pokemon_harvest')->error('Failed to fetch description for ID ' . $data['id']);
      }
          
      // 3. Prepare Node Values
      $node_values = [
        'type' => 'pokedex',
        'title' => ucfirst($data['name']),
        'field_id' => (int)$data['id'],
        'field_hp' => $hp_value,     // Map to hp field
        'field_power' => $attack_value, // Map to attack field
        'field_description' => $description, //description for the pokemon
        'field_evolves_from' => $prev_evo, //PREV EVOLUTION
        'field_evolves_into' => $next_evo, //NEXT EVOL
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