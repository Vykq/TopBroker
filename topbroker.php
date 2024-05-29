<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/wp-load.php");
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Register the custom REST API endpoint
function register_custom_rest_endpoints() {
    register_rest_route(
        'custom/v1',
        '/new-estate/',
        array(
            'methods'  => 'POST',
            'callback' => 'new_estate_top_broker',
            'args'     => array(
                'id' => array(
                    'validate_callback' => function ( $param, $request, $key ) {
                        return $param;
                    },
                ),
            ),
        )
    );

    register_rest_route(
        'custom/v1',
        '/update-estate/',
        array(
            'methods'  => 'POST',
            'callback' => 'update_estate_top_broker',
            'args'     => array(
                'id' => array(
                    'validate_callback' => function ( $param, $request, $key ) {
                        return $param;
                    },
                ),
            ),
        )
    );
}

add_action( 'rest_api_init', 'register_custom_rest_endpoints' );

function new_estate_top_broker($data) {
    error_log('New estate EVENT');
    $payload = $data->get_json_params();
    $id = !empty($payload['payload']['id']) ? $payload['payload']['id'] : null;

    //GET Estate INFO
    if (!empty($id)) {
        $public = isEstatePublic($id);
        if($public === true) {
            $estateInfo = getSingleEstateInfo($id);
            createEstateObject($estateInfo);
            error_log('Sukurtas naujas wp postas NEW estate top broker');
        } else {
            error_log('TOPBROKER NEPUBLIC NK NEDAROM');
        }
    }
}

function update_estate_top_broker($data) {
    error_log('UPDATE EVENT');
    $payload = $data->get_json_params();
    $id = !empty($payload['payload']['id']) ? $payload['payload']['id'] : null;

    //GET Estate INFO
    if (!empty($id)) {
        $public = isEstatePublic($id);

        $args = array(
            'post_type'      => 'parduodami-nt',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => 'topbroker_id', // Adjust this based on your actual ACF field name
                    'value'   => $id,
                    'compare' => '=',
                ),
            ),
            'post_status'    => array('publish', 'draft'),
        );

        $oldPosts = new WP_Query($args);
        $oldID = '';
        $postExists = false;
        $topbrokerID = '';

        if ($oldPosts->have_posts()) {
            $oldPosts->the_post();
            $oldID = get_the_ID();
            $topbrokerID = get_field('topbroker_id', $oldID);
            $postExists = true;
            wp_reset_postdata();
        } else {
            $oldID = null;
        }


            if($postExists) {
                error_log('Rastas senas id' . $oldID);
                if ($public === true) {
                    error_log('Rastas senas id ir darom i publish' . $oldID);
                    $post = array('ID' => $oldID, 'post_status' => 'publish');
                    wp_update_post($post);
                $estate = getSingleEstateInfo($id);

                    if ($estate['type'] == "flat") {
                        wp_set_object_terms($oldID, 16, 'types');
                    } elseif ($estate['type'] == "house") {
                        wp_set_object_terms($oldID, 15, 'types');
                    } elseif ($estate['type'] == "site") {
                        wp_set_object_terms($oldID, 17, 'types');
                    } else {
                        wp_set_object_terms($oldID, 18, 'types');
                    }

                    if ($estate['record_status_id'] == '305') {
                        update_field('rezervuotas', false, $oldID);
                        update_field('parduotas', false, $oldID);
                    }

                    if ($estate['record_status_id'] == '306') {
                        update_field('rezervuotas', true, $oldID);
                        update_field('parduotas', false, $oldID);
                    }

                    if ($estate['record_status_id'] == '307') {
                        update_field('rezervuotas', false, $oldID);
                        update_field('parduotas', true, $oldID);
                    }

                    if ($estate['sale_price'] !== get_field('kaina', $oldID)) {
                        update_field('kaina', $estate['sale_price'], $oldID);
                    }

                    if ($estate['m2'] !== get_field('plotas', $oldID)) {
                        update_field('plotas', $estate['m2'], $oldID);
                    }

                    if ($estate['title'] !== get_the_title($oldID)) {
                        $data = array(
                            'ID' => $oldID,
                            'post_title' => $estate['title']
                        );
                        wp_update_post($data);
                    }

                    if ($estate['description'] !== get_the_content($oldID)) {
                        $data = array(
                            'ID' => $oldID,
                            'post_content' => $estate['description']
                        );
                        wp_update_post($data);
                    }
                    delete_field('trumpi_duomenys', $oldID);


                    $info = "trumpi_duomenys";
                    $value = array(
                        array(
                            "pavadinimas" => "Adresas: ",
                            "reiksme" => $estate['address']
                        )
                    );

                    if ($estate['type'] !== "site") {
                        $value[] = array(
                            "pavadinimas" => "Pastato tipas: ",
                            "reiksme" => $estate['building_type']
                        );
                    }

                    if ($estate['rooms'] !== "") {
                        $value[] = array(
                            "pavadinimas" => "Kambarių sk: ",
                            "reiksme" => $estate['rooms']
                        );
                    }

                    if ($estate['floor'] !== null) {
                        $value[] = array(
                            "pavadinimas" => "Aukštas: ",
                            "reiksme" => $estate['floor'] . '/' . $estate['floorOf']
                        );
                    }

                    if ($estate['year'] !== null) {
                        $value[] = array(
                            "pavadinimas" => "Statybos metai: ",
                            "reiksme" => $estate['year']
                        );
                    }

                    if ($estate['heating'] !== null) {
                        $value[] = array(
                            "pavadinimas" => "Šildymas: ",
                            "reiksme" => $estate['heating']
                        );
                    }

                    if ($estate['type'] == "flat") {
                        $value[] = array(
                            "pavadinimas" => "Vandentiekis: ",
                            "reiksme" => "Miesto"
                        );
                    }

                    if ($estate['type'] == "house") {
                        $value[] = array(
                            "pavadinimas" => "Aukštų sk.: ",
                            "reiksme" => $estate['floorOf']
                        );
                    }

                    if ($estate['type'] == "flat") {
                        $value[] = array(
                            "pavadinimas" => "Kanalizacija: ",
                            "reiksme" => "Miesto"
                        );
                    }

                    if ($estate['site_area']) {
                        $value[] = array(
                            "pavadinimas" => "Sklypo plotas: ",
                            "reiksme" => $estate['site_area'] . ' a.'
                        );
                    }

                    if ($estate['garage_area']) {
                        $value[] = array(
                            "pavadinimas" => "Garažo plotas: ",
                            "reiksme" => $estate['garage_area'] . ' kv.m.'
                        );
                    }

                    if ($estate['garage_cars_fit']) {
                        $value[] = array(
                            "pavadinimas" => "Vietų sk. garaže: ",
                            "reiksme" => $estate['garage_cars_fit']
                        );
                    }

                    if ($estate['has_city_plumbing']) {
                        $value[] = array(
                            "pavadinimas" => "Kanalizacija: ",
                            "reiksme" => 'Miesto'
                        );
                    }
                    update_field($info, $value, $oldID);

                    if(!has_post_thumbnail($oldID)){
                        $photoID = media_sideload_image($estate['photos'][0], $oldID, '', 'id');
                        set_post_thumbnail($oldID, $photoID);
                    }

                    $gallery = get_field('gallery', $oldID);
                    array_shift($estate['photos']);
                    $apiPhotoCount = count($estate['photos']);
                    $noGallery = false;
                    $galleryPhotoCount = 0;
                    if (is_array($gallery)) {
                        $galleryPhotoCount = count($gallery);
                    } else {
                        $noGallery = true;
                    }

                    $galleryImageIds = array();
                    if ($apiPhotoCount !== $galleryPhotoCount || $noGallery === true) {
                        foreach ($estate['photos'] as $photoURL) {
                            $photoID = media_sideload_image($photoURL, $oldID, '', 'id');
                            array_push($galleryImageIds, $photoID);
                        }
                        update_field('gallery', $galleryImageIds, $oldID);
                    }


                    $agents = get_field('agentas');
                    if ($agents) {
                        foreach ($agents as $agent) {
                            $agent_name = $agent->post_title;
                            if ($agent_name !== $estate['agent_name']) {
                                $agentID = getAgentID($estate['user_id']);
                                update_field('agentas', $agentID, $oldID);
                            }
                        }
                    }


            }else {
                    error_log('Darom draft');
                    if ($oldID) {
                        // Update the post status to draft only if it's not already a draft
                        if (get_post_status($oldID) !== 'draft') {
                            $post = array('ID' => $oldID, 'post_status' => 'draft');
                            wp_update_post($post);
                        }
                    }
            }
        } else {
                error_log('Tokio nera darom nauja');

                if ($public === true) {
                    // Create a new estate object only if it's public
                    $estateInfo = getSingleEstateInfo($id);
                    createEstateObject($estateInfo);
                }
        }



    }
}

function isEstatePublic($id){
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.topbroker.lt/api/v5/estates/' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic MzUxNmU3YjA1ZDc5NzQ0ZmI6OWQ2ZDMyZGEtMWM5ZS00YWFlLWFjMjQtMTVhNzk4M2QwOWNl'
        ),
    ));
    $response = curl_exec($curl);
    echo curl_error($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if(empty($data)){
        return false;
    } else {
        return true;
    }

}

function getSingleEstateInfo($ID) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://app.topbroker.lt/api/v5/estates/' . $ID,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic MzUxNmU3YjA1ZDc5NzQ0ZmI6OWQ2ZDMyZGEtMWM5ZS00YWFlLWFjMjQtMTVhNzk4M2QwOWNl'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);

        $estateData = array();
        $estateData['id'] = $ID;
        $estateData['title'] = ucfirst(str_replace('Parduodamas ', '', $data['title']));
        $estateData['description'] = $data['description']['content'];
        $estateData['m2'] = $data['area'] . ' m²';
        $estateData['sale_price'] = $data['sale_price'] . '  €';
        $estateData['square_sale_price'] = $data['square_sale_price'];
        $estateData['address'] = $data['address'];
        $estateData['type'] = $data['estate_type'];
        $estateData['rooms'] = $data['room_count'];
        $estateData['floor'] = $data['floor'];
        $estateData['floorOf'] = $data['floor_count'];
        $estateData['year'] = $data['year'];
        $estateData['user_id'] = $data['user_id'];
        $estateData['record_status_id'] = $data['record_status_id'];
        $estateData['agent_name'] = getTopBrokerUserName($data['user_id']);

        if($estateData['type'] === "flat"){
            $estateData['building_type'] = $data['flat']['building_type'];
            if ($estateData['building_type'] === 'stone') {
                $estateData['building_type'] = 'Mūras';
            }
            if ($estateData['building_type'] === 'brick_wall') {
                $estateData['building_type'] = 'Blokinis';
            }
            if ($estateData['building_type'] === 'loghouse') {
                $estateData['building_type'] = 'Rąstinis';
            }
            if ($estateData['building_type'] === 'wood') {
                $estateData['building_type'] = 'Medinis';
            }
            if ($estateData['building_type'] === 'monolithic') {
                $estateData['building_type'] = 'Monolitas';
            }
            if ($estateData['building_type'] === 'framehouse') {
                $estateData['building_type'] = 'Karkasinis';
            }
            if ($estateData['building_type'] === 'other') {
                $estateData['building_type'] = 'Kita';
            }

            $estateData['heating'] = $data['flat']['building_heating'];
            if ($estateData['heating'] === 'central') {
                $estateData['heating'] = 'Centrinis';
            }
            if ($estateData['heating'] === 'solid_fuel') {
                $estateData['heating'] = 'Kietu kuru';
            }
            if ($estateData['heating'] === 'aerothermal') {
                $estateData['heating'] = 'Aeroterminis';
            }
            if ($estateData['heating'] === 'gas') {
                $estateData['heating'] = 'Dujinis';
            }
            if ($estateData['heating'] === 'electric') {
                $estateData['heating'] = 'Elektrinis';
            }
            if ($estateData['heating'] === 'geo') {
                $estateData['heating'] = 'Geoterminis';
            }
            if ($estateData['heating'] === 'liquid_fuel') {
                $estateData['heating'] = 'Skystu kuru';
            }
            if ($estateData['heating'] === 'sunbatteries') {
                $estateData['heating'] = 'Saulės baterijos';
            }
            if ($estateData['heating'] === 'other') {
                $estateData['heating'] = 'Kita';
            }


            $estateData['equipment'] = $data['flat']['building_equipment'];
            if ($estateData['equipment'] === 'full') {
                $estateData['equipment'] = 1;
            }


        }

        if($estateData['type'] === 'house'){
            $estateData['building_type'] = $data['house']['building_material'];

            if ($estateData['building_type'] === 'loghouse') {
                $estateData['building_type'] = 'Rąstinis';
            }
            if ($estateData['building_type'] === 'brick_wall') {
                $estateData['building_type'] = 'Blokinis';
            }
            if ($estateData['building_type'] === 'stone') {
                $estateData['building_type'] = 'Mūrinis';
            }
            if ($estateData['building_type'] === 'wood') {
                $estateData['building_type'] = 'Medinis';
            }
            if ($estateData['building_type'] === 'monolithic') {
                $estateData['building_type'] = 'Monolitas';
            }
            if ($estateData['building_type'] === 'framehouse') {
                $estateData['building_type'] = 'Karkasinis';
            }
            if ($estateData['building_type'] === 'other') {
                $estateData['building_type'] = 'Kita';
            }

            $estateData['heating'] = $data['house']['building_heating'];
            if ($estateData['heating'] === 'central') {
                $estateData['heating'] = 'Centrinis';
            }
            if ($estateData['heating'] === 'solid_fuel') {
                $estateData['heating'] = 'Kietu kuru';
            }
            if ($estateData['heating'] === 'aerothermal') {
                $estateData['heating'] = 'Aeroterminis';
            }
            if ($estateData['heating'] === 'gas') {
                $estateData['heating'] = 'Dujinis';
            }
            if ($estateData['heating'] === 'electric') {
                $estateData['heating'] = 'Elektrinis';
            }
            if ($estateData['heating'] === 'geo') {
                $estateData['heating'] = 'Geoterminis';
            }
            if ($estateData['heating'] === 'liquid_fuel') {
                $estateData['heating'] = 'Skystu kuru';
            }
            if ($estateData['heating'] === 'sunbatteries') {
                $estateData['heating'] = 'Saulės baterijos';
            }
            if ($estateData['heating'] === 'other') {
                $estateData['heating'] = 'Kita';
            }


            $estateData['equipment'] = $data['house']['building_equipment'];
            if ($estateData['equipment'] === 'full') {
            }

            $estateData['site_area'] = $data['house']['site_area'];
            $estateData['garage_area'] = $data['house']['garage_area'];
            $estateData['garage_cars_fit'] = $data['house']['garage_cars_fit'];

        }

        $estateData['photos'] = $data['photos'];

    return $estateData;


}


function createEstateObject($estate){


            $post_id = wp_insert_post(array (
                'post_type' => 'parduodami-nt',
                'post_title' => $estate['title'],
                'post_content' => $estate['description'],
                'post_status' => 'publish',
            ));

            if ($post_id) {
                update_field('topbroker_id', $estate['id'], $post_id);
                // insert post meta
                if($estate['type'] == "flat") {
                    wp_set_object_terms( $post_id, 16, 'types' );
                }elseif($estate['type'] == "house") {
                    wp_set_object_terms( $post_id, 15, 'types' );
                }elseif($estate['type'] == "site") {
                    wp_set_object_terms( $post_id, 17, 'types' );
                } else {
                    wp_set_object_terms( $post_id, 18, 'types' );
                }

                $imageids = array();
                foreach ($estate['photos'] as $photoURL){
                    $photoID = media_sideload_image($photoURL, $post_id, '', 'id');
                    array_push($imageids, $photoID);
                }
                $featuredimage = array_shift($imageids);
                set_post_thumbnail($post_id, $featuredimage);


                update_field('plotas', $estate['m2'], $post_id);
                update_field('kaina', $estate['sale_price'], $post_id);


                $info = "trumpi_duomenys";
                $value = array(
                    array(
                        "pavadinimas" => "Adresas: ",
                        "reiksme" => $estate['address']
                    )
                );

                if ($estate['type'] !== "site") {
                    $value[] = array(
                        "pavadinimas" => "Pastato tipas: ",
                        "reiksme" => $estate['building_type']
                    );
                }

                if ($estate['rooms'] !== "") {
                    $value[] = array(
                        "pavadinimas" => "Kambarių sk: ",
                        "reiksme" => $estate['rooms']
                    );
                }

                if ($estate['floor'] !== null) {
                    $value[] = array(
                        "pavadinimas" => "Aukštas: ",
                        "reiksme" => $estate['floor'] . '/' . $estate['floorOf']
                    );
                }

                if ($estate['year'] !== null) {
                    $value[] = array(
                        "pavadinimas" => "Statybos metai: ",
                        "reiksme" => $estate['year']
                    );
                }

                if ($estate['heating'] !== null) {
                    $value[] = array(
                        "pavadinimas" => "Šildymas: ",
                        "reiksme" => $estate['heating']
                    );
                }

                if ($estate['type'] == "flat") {
                    $value[] = array(
                        "pavadinimas" => "Vandentiekis: ",
                        "reiksme" => "Miesto"
                    );
                }

                if ($estate['type'] == "house") {
                    $value[] = array(
                        "pavadinimas" => "Aukštų sk.: ",
                        "reiksme" => $estate['floorOf']
                    );
                }

                if ($estate['type'] == "flat") {
                    $value[] = array(
                        "pavadinimas" => "Kanalizacija: ",
                        "reiksme" => "Miesto"
                    );
                }

                if ($estate['site_area']) {
                    $value[] = array(
                        "pavadinimas" => "Sklypo plotas: ",
                        "reiksme" => $estate['site_area'] . ' a.'
                    );
                }

                if ($estate['garage_area']) {
                    $value[] = array(
                        "pavadinimas" => "Garažo plotas: ",
                        "reiksme" => $estate['garage_area'] . ' kv.m.'
                    );
                }

                if ($estate['garage_cars_fit']) {
                    $value[] = array(
                        "pavadinimas" => "Vietų sk. garaže: ",
                        "reiksme" => $estate['garage_cars_fit']
                    );
                }

                if ($estate['has_city_plumbing']) {
                    $value[] = array(
                        "pavadinimas" => "Kanalizacija: ",
                        "reiksme" => 'Miesto'
                    );
                }

                update_field($info, $value, $post_id);

                $agentID = getAgentID($estate['agent_name']);
                if($agentID == ''){
                    $agentID = 37;
                }
                update_field('agentas', $agentID, $post_id);
                update_field( 'gallery', $imageids , $post_id );

            }
}


function getTopBrokerUserName($user_id) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.topbroker.lt/api/v5/users/' . $user_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic MzUxNmU3YjA1ZDc5NzQ0ZmI6OWQ2ZDMyZGEtMWM5ZS00YWFlLWFjMjQtMTVhNzk4M2QwOWNl'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    $agent_name = $data['name'];
    return $agent_name;
}


function getAgentID( $title = '' ) {
    if ( $title === '' ) {
        return get_the_ID();
    }

    $post_object = get_page_by_title( $title, OBJECT, 'ekspertas' );

    return $post_object->ID;
}
