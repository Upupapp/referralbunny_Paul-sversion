<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the LGU IDS tenant's Organizations table with all Philippine
 * cities and municipalities, organized by province.
 *
 * Duplicate municipality/city names across different provinces are
 * listed separately as distinct records.
 *
 * Usage:
 *   php artisan db:seed --class=PhilippineLGUSeeder
 */
class PhilippineLGUSeeder extends Seeder
{
    const TENANT_ID = 'lgu-ids';

    public function run(): void
    {
        $existing = DB::table('organizations')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        if ($existing > 0) {
            $this->command->warn("Organizations already seeded for LGU IDS ({$existing} records). Skipping.");
            return;
        }

        $lgus = $this->getAllLGUs();

        $this->command->info("Seeding " . count($lgus) . " Philippine cities and municipalities…");

        $chunks = array_chunk($lgus, 100);
        $total  = 0;

        foreach ($chunks as $chunk) {
            $rows = array_map(fn($lgu) => [
                'id'         => (string) Str::uuid(),
                'tenant_id'  => self::TENANT_ID,
                'name'       => $lgu['type'] === 'City'
                                    ? 'City of ' . $lgu['name']
                                    : 'Municipality of ' . $lgu['name'],
                'industry'   => 'Government / LGU',
                'city'       => $lgu['name'],
                'address'    => $lgu['province'],
                'country'    => 'Philippines',
                'notes'      => $lgu['type'] . ' · ' . $lgu['province'],
                'data'       => json_encode([
                    'lgu_type' => $lgu['type'],
                    'province' => $lgu['province'],
                    'region'   => $lgu['region'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk);

            DB::table('organizations')->insert($rows);
            $total += count($rows);
        }

        $this->command->info("Done. {$total} LGUs inserted for LGU IDS tenant.");
    }

    // ── Complete Philippine LGU dataset ──────────────────────────────
    private function getAllLGUs(): array
    {
        $data = [];

        foreach ($this->provinces() as $province => $entries) {
            $region = $entries['region'];
            foreach ($entries['cities'] ?? [] as $city) {
                $data[] = ['name' => $city, 'type' => 'City', 'province' => $province, 'region' => $region];
            }
            foreach ($entries['municipalities'] ?? [] as $mun) {
                $data[] = ['name' => $mun, 'type' => 'Municipality', 'province' => $province, 'region' => $region];
            }
        }

        return $data;
    }

    private function provinces(): array
    {
        return [

            // ══ NATIONAL CAPITAL REGION (NCR) ════════════════════════
            'Metro Manila' => [
                'region' => 'NCR',
                'cities' => [
                    'Manila','Caloocan','Las Piñas','Makati','Malabon','Mandaluyong',
                    'Marikina','Muntinlupa','Navotas','Parañaque','Pasay','Pasig',
                    'Quezon City','San Juan','Taguig','Valenzuela',
                ],
                'municipalities' => ['Pateros'],
            ],

            // ══ REGION I — ILOCOS REGION ══════════════════════════════
            'Ilocos Norte' => [
                'region' => 'Region I',
                'cities' => ['Laoag'],
                'municipalities' => [
                    'Adams','Bacarra','Badoc','Bangui','Banna','Burgos','Carasi','Currimao',
                    'Dingras','Dumalneg','Marcos','Nueva Era','Pagudpud','Paoay','Pasuquin',
                    'Piddig','Pinili','San Nicolas','Sarrat','Solsona','Vintar',
                ],
            ],
            'Ilocos Sur' => [
                'region' => 'Region I',
                'cities' => ['Candon','Vigan'],
                'municipalities' => [
                    'Alilem','Banayoyo','Bantay','Burgos','Cabugao','Caoayan','Cervantes',
                    'Galimuyod','Gregorio del Pilar','Lidlidda','Magsingal','Nagbukel',
                    'Narvacan','Quirino','Salcedo','San Emilio','San Esteban','San Ildefonso',
                    'San Juan','San Vicente','Santa','Santa Catalina','Santa Cruz','Santa Lucia',
                    'Santa Maria','Santiago','Sigay','Sinait','Sugpon','Suyo','Tagudin',
                ],
            ],
            'La Union' => [
                'region' => 'Region I',
                'cities' => ['San Fernando'],
                'municipalities' => [
                    'Agoo','Aringay','Bacnotan','Balaoan','Bangar','Bauang','Burgos',
                    'Caba','Luna','Naguilian','Pugo','Rosario','San Gabriel','San Juan',
                    'Santo Tomas','Santol','Sudipen','Tubao',
                ],
            ],
            'Pangasinan' => [
                'region' => 'Region I',
                'cities' => ['Alaminos','Dagupan','San Carlos','Urdaneta'],
                'municipalities' => [
                    'Agno','Aguilar','Alcala','Anda','Asingan','Balungao','Bani','Basista',
                    'Bautista','Bayambang','Binalonan','Binmaley','Bolinao','Bugallon',
                    'Burgos','Calasiao','Dasol','Infanta','Labrador','Laoac','Lingayen',
                    'Mabini','Malasiqui','Manaoag','Mangaldan','Mangatarem','Mapandan',
                    'Natividad','Pozorrubio','Rosales','San Fabian','San Jacinto','San Manuel',
                    'San Nicolas','San Quintin','Santa Barbara','Santa Maria','Santo Tomas',
                    'Sison','Sual','Tayug','Umingan','Urbiztondo','Villasis',
                ],
            ],

            // ══ REGION II — CAGAYAN VALLEY ════════════════════════════
            'Batanes' => [
                'region' => 'Region II',
                'cities' => [],
                'municipalities' => ['Basco','Itbayat','Ivana','Mahatao','Sabtang','Uyugan'],
            ],
            'Cagayan' => [
                'region' => 'Region II',
                'cities' => ['Tuguegarao'],
                'municipalities' => [
                    'Abulug','Alcala','Allacapan','Amulung','Aparri','Baggao','Ballesteros',
                    'Buguey','Calayan','Camalaniugan','Claveria','Enrile','Gattaran',
                    'Gonzaga','Iguig','Lal-lo','Lasam','Pamplona','Peñablanca','Piat',
                    'Rizal','Sanchez-Mira','Santa Ana','Santa Praxedes','Santa Teresita',
                    'Santo Niño','Solana','Tuao',
                ],
            ],
            'Isabela' => [
                'region' => 'Region II',
                'cities' => ['Cauayan','Ilagan','Santiago'],
                'municipalities' => [
                    'Alicia','Angadanan','Aurora','Benito Soliven','Burgos','Cabagan',
                    'Cabatuan','Cordon','Delfin Albano','Dinapigue','Divilacan','Echague',
                    'Gamu','Jones','Luna','Maconacon','Mallig','Naguilian','Palanan',
                    'Quezon','Quirino','Ramon','Reina Mercedes','Roxas','San Agustin',
                    'San Guillermo','San Isidro','San Manuel','San Mariano','San Mateo',
                    'San Pablo','Santa Maria','Santo Tomas','Tumauini',
                ],
            ],
            'Nueva Vizcaya' => [
                'region' => 'Region II',
                'cities' => [],
                'municipalities' => [
                    'Alfonso Castañeda','Ambaguio','Aritao','Bagabag','Bambang','Bayombong',
                    'Diadi','Dupax del Norte','Dupax del Sur','Kasibu','Kayapa','Quezon',
                    'Santa Fe','Solano','Villaverde',
                ],
            ],
            'Quirino' => [
                'region' => 'Region II',
                'cities' => [],
                'municipalities' => [
                    'Aglipay','Cabarroguis','Diffun','Maddela','Nagtipunan','Saguday',
                ],
            ],

            // ══ REGION III — CENTRAL LUZON ════════════════════════════
            'Aurora' => [
                'region' => 'Region III',
                'cities' => [],
                'municipalities' => [
                    'Baler','Casiguran','Dilasag','Dinalungan','Dingalan','Dipaculao',
                    'Maria Aurora','San Luis',
                ],
            ],
            'Bataan' => [
                'region' => 'Region III',
                'cities' => ['Balanga'],
                'municipalities' => [
                    'Abucay','Bagac','Dinalupihan','Hermosa','Limay','Mariveles',
                    'Morong','Orani','Orion','Pilar','Samal',
                ],
            ],
            'Bulacan' => [
                'region' => 'Region III',
                'cities' => ['Malolos','Meycauayan','San Jose del Monte'],
                'municipalities' => [
                    'Angat','Balagtas','Baliuag','Bocaue','Bulakan','Bustos','Calumpit',
                    'Doña Remedios Trinidad','Guiguinto','Hagonoy','Marilao','Norzagaray',
                    'Obando','Pandi','Paombong','Plaridel','Pulilan','San Ildefonso',
                    'San Miguel','San Rafael','Santa Maria',
                ],
            ],
            'Nueva Ecija' => [
                'region' => 'Region III',
                'cities' => ['Cabanatuan','Gapan','Palayan','San Jose','Science City of Muñoz'],
                'municipalities' => [
                    'Aliaga','Bongabon','Cabiao','Carranglan','Cuyapo','Gabaldon',
                    'General Mamerto Natividad','General Tinio','Guimba','Jaen','Laur',
                    'Licab','Llanera','Lupao','Nampicuan','Pantabangan','Peñaranda',
                    'Quezon','Rizal','San Antonio','San Isidro','San Leonardo','Santa Rosa',
                    'Santo Domingo','Talavera','Talugtug','Zaragoza',
                ],
            ],
            'Pampanga' => [
                'region' => 'Region III',
                'cities' => ['Angeles','Mabalacat','San Fernando'],
                'municipalities' => [
                    'Apalit','Arayat','Bacolor','Candaba','Floridablanca','Guagua',
                    'Lubao','Macabebe','Magalang','Masantol','Mexico','Minalin','Porac',
                    'San Luis','San Simon','Santa Ana','Santa Rita','Santo Tomas','Sasmuan',
                ],
            ],
            'Tarlac' => [
                'region' => 'Region III',
                'cities' => ['Tarlac'],
                'municipalities' => [
                    'Anao','Bamban','Camiling','Capas','Concepcion','Gerona','La Paz',
                    'Mayantoc','Moncada','Paniqui','Pura','Ramos','San Clemente',
                    'San Jose','San Manuel','Santa Ignacia','Victoria',
                ],
            ],
            'Zambales' => [
                'region' => 'Region III',
                'cities' => ['Olongapo'],
                'municipalities' => [
                    'Botolan','Cabangan','Candelaria','Castillejos','Iba','Masinloc',
                    'Palauig','San Antonio','San Felipe','San Marcelino','San Narciso',
                    'Santa Cruz','Subic',
                ],
            ],

            // ══ REGION IV-A — CALABARZON ══════════════════════════════
            'Batangas' => [
                'region' => 'Region IV-A',
                'cities' => ['Batangas','Lipa','Tanauan'],
                'municipalities' => [
                    'Agoncillo','Alitagtag','Balayan','Balete','Bauan','Calaca','Calatagan',
                    'Cuenca','Ibaan','Laurel','Lemery','Lian','Lobo','Mabini','Malvar',
                    'Mataas na Kahoy','Nasugbu','Padre Garcia','Rosario','San Jose',
                    'San Juan','San Luis','San Nicolas','San Pascual','Santa Teresita',
                    'Santo Tomas','Taal','Taysan','Tingloy','Tuy',
                ],
            ],
            'Cavite' => [
                'region' => 'Region IV-A',
                'cities' => [
                    'Bacoor','Cavite','Dasmariñas','General Trias','Imus',
                    'Tagaytay','Trece Martires',
                ],
                'municipalities' => [
                    'Alfonso','Amadeo','Carmona','General Emilio Aguinaldo','General Mariano Alvarez',
                    'Indang','Kawit','Magallanes','Maragondon','Mendez','Naic','Noveleta',
                    'Rosario','Silang','Tanza','Ternate',
                ],
            ],
            'Laguna' => [
                'region' => 'Region IV-A',
                'cities' => ['Biñan','Cabuyao','Calamba','San Pablo','San Pedro','Santa Rosa'],
                'municipalities' => [
                    'Alaminos','Bay','Calauan','Cavinti','Famy','Kalayaan','Liliw','Los Baños',
                    'Luisiana','Lumban','Mabitac','Magdalena','Majayjay','Nagcarlan','Paete',
                    'Pagsanjan','Pakil','Pangil','Pila','Rizal','Santa Cruz','Santa Maria',
                    'Siniloan','Victoria',
                ],
            ],
            'Quezon' => [
                'region' => 'Region IV-A',
                'cities' => ['Lucena','Tayabas'],
                'municipalities' => [
                    'Agdangan','Alabat','Atimonan','Buenavista','Burdeos','Calauag',
                    'Candelaria','Catanauan','Dolores','General Luna','General Nakar',
                    'Guinayangan','Gumaca','Infanta','Jomalig','Lopez','Lucban','Macalelon',
                    'Mauban','Mulanay','Padre Burgos','Pagbilao','Panukulan','Patnanungan',
                    'Perez','Pitogo','Plaridel','Polillo','Quezon','Real','Sampaloc',
                    'San Andres','San Antonio','San Francisco','San Narciso','Sariaya',
                    'Tagkawayan','Tiaong','Unson',
                ],
            ],
            'Rizal' => [
                'region' => 'Region IV-A',
                'cities' => ['Antipolo'],
                'municipalities' => [
                    'Angono','Baras','Binangonan','Cainta','Cardona','Jala-Jala',
                    'Morong','Pililla','Rodriguez','San Mateo','Taytay','Teresa',
                ],
            ],

            // ══ REGION IV-B — MIMAROPA ════════════════════════════════
            'Marinduque' => [
                'region' => 'Region IV-B',
                'cities' => [],
                'municipalities' => [
                    'Boac','Buenavista','Gasan','Mogpog','Santa Cruz','Torrijos',
                ],
            ],
            'Occidental Mindoro' => [
                'region' => 'Region IV-B',
                'cities' => [],
                'municipalities' => [
                    'Abra de Ilog','Calintaan','Looc','Lubang','Magsaysay','Mamburao',
                    'Paluan','Rizal','Sablayan','San Jose','Santa Cruz',
                ],
            ],
            'Oriental Mindoro' => [
                'region' => 'Region IV-B',
                'cities' => [],
                'municipalities' => [
                    'Baco','Bansud','Bongabong','Bulalacao','Calapan','Gloria','Mansalay',
                    'Naujan','Pinamalayan','Pola','Puerto Galera','Roxas','San Teodoro',
                    'Socorro','Victoria',
                ],
            ],
            'Palawan' => [
                'region' => 'Region IV-B',
                'cities' => ['Puerto Princesa'],
                'municipalities' => [
                    'Aborlan','Agutaya','Araceli','Balabac','Bataraza','Brooke\'s Point',
                    'Busuanga','Cagayancillo','Coron','Culion','Cuyo','Dumaran','El Nido',
                    'Española','Kalayaan','Linapacan','Magsaysay','Narra','Quezon',
                    'Rizal','Roxas','San Vicente','Sofronio Española','Taytay',
                ],
            ],
            'Romblon' => [
                'region' => 'Region IV-B',
                'cities' => [],
                'municipalities' => [
                    'Alcantara','Banton','Cajidiocan','Calatrava','Concepcion','Corcuera',
                    'Ferrol','Looc','Magdiwang','Odiongan','Romblon','San Agustin',
                    'San Andres','San Fernando','San Jose','Santa Fe','Santa Maria',
                ],
            ],

            // ══ REGION V — BICOL REGION ═══════════════════════════════
            'Albay' => [
                'region' => 'Region V',
                'cities' => ['Legazpi','Ligao','Tabaco'],
                'municipalities' => [
                    'Bacacay','Camalig','Daraga','Guinobatan','Jovellar','Libon','Malilipot',
                    'Malinao','Manito','Oas','Pio Duran','Polangui','Rapu-Rapu','Santo Domingo',
                    'Tiwi',
                ],
            ],
            'Camarines Norte' => [
                'region' => 'Region V',
                'cities' => [],
                'municipalities' => [
                    'Basud','Capalonga','Daet','Jose Panganiban','Labo','Mercedes',
                    'Paracale','San Lorenzo Ruiz','San Vicente','Santa Elena','Talisay',
                    'Vinzons',
                ],
            ],
            'Camarines Sur' => [
                'region' => 'Region V',
                'cities' => ['Iriga','Naga'],
                'municipalities' => [
                    'Baao','Balatan','Bato','Bombon','Buhi','Bula','Cabusao','Calabanga',
                    'Camaligan','Canaman','Caramoan','Del Gallego','Gainza','Garchitorena',
                    'Goa','Lagonoy','Libmanan','Lupi','Magarao','Milaor','Minalabac',
                    'Nabua','Ocampo','Pamplona','Pasacao','Pili','Presentacion','Ragay',
                    'Sagñay','San Fernando','San Jose','Sipocot','Siruma','Tigaon','Tinambac',
                ],
            ],
            'Catanduanes' => [
                'region' => 'Region V',
                'cities' => [],
                'municipalities' => [
                    'Bagamanoc','Baras','Bato','Caramoran','Gigmoto','Pandan','Panganiban',
                    'San Andres','San Miguel','Viga','Virac',
                ],
            ],
            'Masbate' => [
                'region' => 'Region V',
                'cities' => ['Masbate'],
                'municipalities' => [
                    'Aroroy','Baleno','Balud','Batuan','Cataingan','Cawayan','Claveria',
                    'Dimasalang','Esperanza','Mandaon','Milagros','Mobo','Monreal',
                    'Palanas','Pio V. Corpuz','Placer','San Fernando','San Jacinto',
                    'San Pascual','Uson',
                ],
            ],
            'Sorsogon' => [
                'region' => 'Region V',
                'cities' => ['Sorsogon'],
                'municipalities' => [
                    'Barcelona','Bulan','Bulusan','Casiguran','Castilla','Donsol',
                    'Gubat','Irosin','Juban','Magallanes','Matnog','Pilar','Prieto Diaz',
                    'Santa Magdalena',
                ],
            ],

            // ══ REGION VI — WESTERN VISAYAS ═══════════════════════════
            'Aklan' => [
                'region' => 'Region VI',
                'cities' => [],
                'municipalities' => [
                    'Altavas','Balete','Banga','Batan','Buruanga','Ibajay','Kalibo',
                    'Lezo','Libacao','Madalag','Makato','Malay','Malinao','Nabas',
                    'New Washington','Numancia','Tangalan',
                ],
            ],
            'Antique' => [
                'region' => 'Region VI',
                'cities' => [],
                'municipalities' => [
                    'Anini-y','Barbaza','Belison','Bugasong','Caluya','Culasi','Hamtic',
                    'Laua-an','Libertad','Pandan','Patnongon','San Jose de Buenavista',
                    'San Remigio','Sebaste','Sibalom','Tibiao','Tobias Fornier','Valderrama',
                ],
            ],
            'Capiz' => [
                'region' => 'Region VI',
                'cities' => ['Roxas'],
                'municipalities' => [
                    'Cuartero','Dao','Dumalag','Dumarao','Ivisan','Jamindan','Ma-ayon',
                    'Mambusao','Panay','Panitan','Pilar','Pontevedra','President Roxas',
                    'Sapi-an','Sigma','Tapaz',
                ],
            ],
            'Guimaras' => [
                'region' => 'Region VI',
                'cities' => [],
                'municipalities' => [
                    'Buenavista','Jordan','Nueva Valencia','San Lorenzo','Sibunag',
                ],
            ],
            'Iloilo' => [
                'region' => 'Region VI',
                'cities' => ['Iloilo','Passi'],
                'municipalities' => [
                    'Ajuy','Alimodian','Anilao','Badiangan','Balasan','Banate','Barotac Nuevo',
                    'Barotac Viejo','Batad','Bingawan','Cabatuan','Calinog','Carles','Concepcion',
                    'Dingle','Dueñas','Dumangas','Estancia','Guimbal','Igbaras','Janiuay',
                    'Lambunao','Leganes','Lemery','Leon','Maasin','Miagao','Mina','New Lucena',
                    'Oton','Pavia','Pototan','San Dionisio','San Enrique','San Joaquin',
                    'San Miguel','San Rafael','Santa Barbara','Sara','Tigbauan','Tubungan','Zarraga',
                ],
            ],
            'Negros Occidental' => [
                'region' => 'Region VI',
                'cities' => [
                    'Bago','Cadiz','Canlaon','Escalante','Himamaylan','Kabankalan',
                    'La Carlota','Sagay','San Carlos','Silay','Sipalay','Talisay',
                    'Victorias','Bacolod',
                ],
                'municipalities' => [
                    'Binalbagan','Calatrava','Candoni','Cauayan','Enrique B. Magalona',
                    'Hinigaran','Hinoba-an','Ilog','Isabela','La Castellana','Manapla',
                    'Moises Padilla','Murcia','Pontevedra','Pulupandan','Salvador Benedicto',
                    'San Enrique','Toboso','Valladolid',
                ],
            ],

            // ══ REGION VII — CENTRAL VISAYAS ══════════════════════════
            'Bohol' => [
                'region' => 'Region VII',
                'cities' => ['Tagbilaran'],
                'municipalities' => [
                    'Alburquerque','Alicia','Anda','Antequera','Baclayon','Balilihan',
                    'Batuan','Bien Unido','Bilar','Buenavista','Calape','Candijay',
                    'Carmen','Catigbian','Clarin','Corella','Cortes','Dagohoy','Danao',
                    'Dauis','Dimiao','Duero','Garcia Hernandez','Guindulman','Inabanga',
                    'Jagna','Jetafe','Lila','Loay','Loboc','Loon','Mabini','Maribojoc',
                    'Panglao','Pilar','Pres. Carlos P. Garcia','Sagbayan','San Isidro',
                    'San Miguel','Sevilla','Sierra Bullones','Sikatuna','Talibon',
                    'Trinidad','Tubigon','Ubay','Valencia',
                ],
            ],
            'Cebu' => [
                'region' => 'Region VII',
                'cities' => [
                    'Carcar','Cebu','Danao','Lapu-Lapu','Mandaue','Naga',
                    'Talisay','Toledo',
                ],
                'municipalities' => [
                    'Alcantara','Alcoy','Alegria','Aloguinsan','Argao','Asturias','Badian',
                    'Balamban','Bantayan','Barili','Bogo','Boljoon','Borbon','Carmen',
                    'Catmon','Compostela','Consolacion','Cordova','Daanbantayan','Dalaguete',
                    'Dumanjug','Ginatilan','Liloan','Madridejos','Malabuyoc','Medellin',
                    'Minglanilla','Moalboal','Oslob','Pilar','Pinamungahan','Poro',
                    'Ronda','Samboan','San Fernando','San Francisco','San Remigio',
                    'Santa Fe','Santander','Sibonga','Sogod','Tabogon','Tabuelan',
                    'Tuburan','Tudela',
                ],
            ],
            'Negros Oriental' => [
                'region' => 'Region VII',
                'cities' => ['Bais','Bayawan','Canlaon','Dumaguete','Guihulngan','Tanjay'],
                'municipalities' => [
                    'Amlan','Ayungon','Bacong','Basay','Bindoy','Dauin','Jimalalud',
                    'La Libertad','Mabinay','Manjuyod','Pamplona','San Jose','Santa Catalina',
                    'Siaton','Sibulan','Tayasan','Valencia','Vallehermoso','Zamboanguita',
                ],
            ],
            'Siquijor' => [
                'region' => 'Region VII',
                'cities' => [],
                'municipalities' => [
                    'Enrique Villanueva','Larena','Lazi','Maria','San Juan','Siquijor',
                ],
            ],

            // ══ REGION VIII — EASTERN VISAYAS ═════════════════════════
            'Biliran' => [
                'region' => 'Region VIII',
                'cities' => [],
                'municipalities' => [
                    'Almeria','Biliran','Cabucgayan','Caibiran','Culaba','Kawayan',
                    'Maripipi','Naval',
                ],
            ],
            'Eastern Samar' => [
                'region' => 'Region VIII',
                'cities' => [],
                'municipalities' => [
                    'Arteche','Balangiga','Balangkayan','Borongan','Can-avid','Dolores',
                    'General MacArthur','Giporlos','Guiuan','Hernani','Jipapad','Lawaan',
                    'Llorente','Maslog','Maydolong','Mercedes','Oras','Quinapondan',
                    'Salcedo','San Julian','San Policarpo','Sulat','Taft',
                ],
            ],
            'Leyte' => [
                'region' => 'Region VIII',
                'cities' => ['Baybay','Ormoc','Tacloban'],
                'municipalities' => [
                    'Abuyog','Alangalang','Albuera','Babatngon','Barugo','Bato',
                    'Burauen','Calubian','Capoocan','Carigara','Dagami','Dulag',
                    'Hilongos','Hindang','Inopacan','Isabel','Jaro','Javier','Julita',
                    'Kananga','La Paz','Leyte','MacArthur','Mahaplag','Matag-ob',
                    'Matalom','Mayorga','Merida','Palo','Palompon','Pastrana',
                    'San Isidro','San Miguel','Santa Fe','Tabango','Tabontabon',
                    'Tanauan','Tolosa','Tunga','Villaba',
                ],
            ],
            'Northern Samar' => [
                'region' => 'Region VIII',
                'cities' => ['Catarman'],
                'municipalities' => [
                    'Allen','Biri','Bobon','Capul','Catubig','Gamay','Laoang','Lapinig',
                    'Las Navas','Lavezares','Lope de Vega','Mapanas','Mondragon','Palapag',
                    'Pambujan','Rosario','San Antonio','San Isidro','San Jose','San Roque',
                    'San Vicente','Silvino Lobos','Victoria',
                ],
            ],
            'Samar' => [
                'region' => 'Region VIII',
                'cities' => ['Calbayog','Catbalogan'],
                'municipalities' => [
                    'Almagro','Basey','Calbiga','Daram','Gandara','Hinabangan','Jiabong',
                    'Marabut','Matuguinao','Motiong','Pagsanghan','Paranas','Pinabacdao',
                    'San Jorge','San Jose de Buan','San Sebastian','Santa Margarita',
                    'Santa Rita','Santo Niño','Tagapul-an','Talalora','Tarangnan',
                    'Villareal','Zumarraga',
                ],
            ],
            'Southern Leyte' => [
                'region' => 'Region VIII',
                'cities' => ['Maasin'],
                'municipalities' => [
                    'Anahawan','Bontoc','Hinunangan','Hinundayan','Libagon','Liloan',
                    'Limasawa','Macrohon','Malitbog','Padre Burgos','Pintuyan','Saint Bernard',
                    'San Francisco','San Juan','San Ricardo','Silago','Sogod','Tomas Oppus',
                ],
            ],

            // ══ REGION IX — ZAMBOANGA PENINSULA ═══════════════════════
            'Zamboanga del Norte' => [
                'region' => 'Region IX',
                'cities' => ['Dapitan','Dipolog'],
                'municipalities' => [
                    'Baliguian','Godod','Gutalac','Jose Dalman','Kalawit','Katipunan',
                    'La Libertad','Labason','Leon B. Postigo','Liloy','Manukan',
                    'Mutia','Piñan','Polanco','President Manuel A. Roxas','Rizal',
                    'Salug','San Miguel','Sergio Osmeña Sr.','Siayan','Sibuco',
                    'Sibutad','Sindangan','Siocon','Sirawai','Tampilisan',
                ],
            ],
            'Zamboanga del Sur' => [
                'region' => 'Region IX',
                'cities' => ['Pagadian','Zamboanga'],
                'municipalities' => [
                    'Aurora','Bayog','Dimataling','Dinas','Dumalinao','Dumingag',
                    'Guipos','Josefina','Kumalarang','Labangan','Lakewood','Lapuyan',
                    'Mahayag','Margosatubig','Midsalip','Molave','Pitogo','Ramon Magsaysay',
                    'San Miguel','San Pablo','Tabina','Tambulig','Tigbao','Tukuran',
                    'Vincenzo A. Sagun',
                ],
            ],
            'Zamboanga Sibugay' => [
                'region' => 'Region IX',
                'cities' => ['Ipil'],
                'municipalities' => [
                    'Alicia','Buug','Diplahan','Imelda','Kabasalan','Mabuhay',
                    'Malangas','Naga','Olutanga','Payao','Roseller T. Lim','Siay',
                    'Talusan','Tungawan',
                ],
            ],

            // ══ REGION X — NORTHERN MINDANAO ══════════════════════════
            'Bukidnon' => [
                'region' => 'Region X',
                'cities' => ['Malaybalay','Valencia'],
                'municipalities' => [
                    'Baungon','Cabanglasan','Damulog','Dangcagan','Don Carlos','Impasug-ong',
                    'Kadingilan','Kalilangan','Kibawe','Kitaotao','Lantapan','Libona',
                    'Malitbog','Manolo Fortich','Maramag','Pangantucan','Quezon',
                    'San Fernando','Sumilao','Talakag',
                ],
            ],
            'Camiguin' => [
                'region' => 'Region X',
                'cities' => [],
                'municipalities' => ['Catarman','Guinsiliban','Mahinog','Mambajao','Sagay'],
            ],
            'Lanao del Norte' => [
                'region' => 'Region X',
                'cities' => ['Iligan'],
                'municipalities' => [
                    'Bacolod','Baloi','Baroy','Kapatagan','Kauswagan','Kolambugan',
                    'Lala','Linamon','Magsaysay','Maigo','Munai','Nunungan','Pantao Ragat',
                    'Pantar','Poona Piagapo','Salvador','Sapad','Sultan Naga Dimaporo',
                    'Tagoloan','Tangcal','Tubod',
                ],
            ],
            'Misamis Occidental' => [
                'region' => 'Region X',
                'cities' => ['Oroquieta','Ozamiz','Tangub'],
                'municipalities' => [
                    'Aloran','Baliangao','Bonifacio','Calamba','Clarin','Concepcion',
                    'Don Victoriano Chiongbian','Jimenez','Lopez Jaena','Panaon',
                    'Plaridel','Sapang Dalaga','Sinacaban','Tudela',
                ],
            ],
            'Misamis Oriental' => [
                'region' => 'Region X',
                'cities' => ['Cagayan de Oro','El Salvador','Gingoog'],
                'municipalities' => [
                    'Alubijid','Balingasag','Balingoan','Binuangan','Claveria','Gitagum',
                    'Initao','Jasaan','Kinoguitan','Lagonglong','Laguindingan','Libertad',
                    'Lugait','Magsaysay','Manticao','Medina','Naawan','Opol','Salay',
                    'Sugbongcogon','Tagoloan','Talisayan','Villanueva',
                ],
            ],

            // ══ REGION XI — DAVAO REGION ══════════════════════════════
            'Davao de Oro' => [
                'region' => 'Region XI',
                'cities' => [],
                'municipalities' => [
                    'Compostela','Laak','Mabini','Maco','Maragusan','Mawab','Monkayo',
                    'Montevista','Nabunturan','New Bataan','Pantukan',
                ],
            ],
            'Davao del Norte' => [
                'region' => 'Region XI',
                'cities' => ['Panabo','Samal','Tagum'],
                'municipalities' => [
                    'Asuncion','Braulio E. Dujali','Carmen','Kapalong','New Corella',
                    'San Isidro','Santo Tomas','Talaingod',
                ],
            ],
            'Davao del Sur' => [
                'region' => 'Region XI',
                'cities' => ['Davao','Digos'],
                'municipalities' => [
                    'Bansalan','Don Marcelino','Hagonoy','José Abad Santos','Kiblawan',
                    'Magsaysay','Malalag','Matanao','Padada','Santa Cruz','Sulop',
                ],
            ],
            'Davao Occidental' => [
                'region' => 'Region XI',
                'cities' => [],
                'municipalities' => [
                    'Don Marcelino','Jose Abad Santos','Malita','Santa Maria','Sarangani',
                ],
            ],
            'Davao Oriental' => [
                'region' => 'Region XI',
                'cities' => ['Mati'],
                'municipalities' => [
                    'Baganga','Banaybanay','Boston','Caraga','Cateel','Governor Generoso',
                    'Lupon','Manay','San Isidro','Tarragona',
                ],
            ],

            // ══ REGION XII — SOCCSKSARGEN ═════════════════════════════
            'North Cotabato' => [
                'region' => 'Region XII',
                'cities' => ['Cotabato','Kidapawan'],
                'municipalities' => [
                    'Aleosan','Antipas','Arakan','Banisilan','Carmen','Kabacan','Libungan',
                    'M\'lang','Magpet','Makilala','Matalam','Midsayap','Pigkawayan',
                    'Pikit','President Roxas','Tulunan',
                ],
            ],
            'Sarangani' => [
                'region' => 'Region XII',
                'cities' => ['Alabel'],
                'municipalities' => [
                    'Glan','Kiamba','Maasim','Maitum','Malapatan','Malungon',
                ],
            ],
            'South Cotabato' => [
                'region' => 'Region XII',
                'cities' => ['General Santos','Koronadal'],
                'municipalities' => [
                    'Banga','Lake Sebu','Norala','Polomolok','Santo Niño','Surallah',
                    'T\'boli','Tampakan','Tantangan','Tupi',
                ],
            ],
            'Sultan Kudarat' => [
                'region' => 'Region XII',
                'cities' => ['Tacurong'],
                'municipalities' => [
                    'Bagumbayan','Columbio','Esperanza','Isulan','Kalamansig','Lambayong',
                    'Lebak','Lutayan','Palimbang','President Quirino','Senator Ninoy Aquino',
                ],
            ],

            // ══ REGION XIII — CARAGA ══════════════════════════════════
            'Agusan del Norte' => [
                'region' => 'Region XIII',
                'cities' => ['Butuan','Cabadbaran'],
                'municipalities' => [
                    'Buenavista','Carmen','Jabonga','Kitcharao','Las Nieves','Magallanes',
                    'Nasipit','Remedios T. Romualdez','Santiago','Tubay',
                ],
            ],
            'Agusan del Sur' => [
                'region' => 'Region XIII',
                'cities' => ['Bayugan'],
                'municipalities' => [
                    'Bunawan','Esperanza','La Paz','Loreto','Prosperidad','Rosario',
                    'San Francisco','San Luis','Santa Josefa','Sibagat','Talacogon',
                    'Trento','Veruela',
                ],
            ],
            'Dinagat Islands' => [
                'region' => 'Region XIII',
                'cities' => [],
                'municipalities' => [
                    'Basilisa','Cagdianao','Dinagat','Libjo','Loreto','San Jose',
                    'Tubajon',
                ],
            ],
            'Surigao del Norte' => [
                'region' => 'Region XIII',
                'cities' => ['Surigao'],
                'municipalities' => [
                    'Alegria','Bacuag','Burgos','Claver','Dapa','Del Carmen','General Luna',
                    'Gigaquit','Mainit','Malimono','Pilar','Placer','San Benito','San Francisco',
                    'San Isidro','Santa Monica','Sison','Socorro','Tagana-an','Tubod',
                ],
            ],
            'Surigao del Sur' => [
                'region' => 'Region XIII',
                'cities' => ['Bislig','Tandag'],
                'municipalities' => [
                    'Barobo','Bayabas','Cagwait','Cantilan','Carmen','Carrascal','Cortes',
                    'Hinatuan','Lanuza','Lianga','Lingig','Madrid','Marihatag','San Agustin',
                    'San Miguel','Tagbina','Tago',
                ],
            ],

            // ══ CAR — CORDILLERA ADMINISTRATIVE REGION ════════════════
            'Abra' => [
                'region' => 'CAR',
                'cities' => [],
                'municipalities' => [
                    'Bangued','Boliney','Bucay','Bucloc','Daguioman','Danglas','Dolores',
                    'La Paz','Lacub','Lagangilang','Lagayan','Langiden','Luba','Malibcong',
                    'Manabo','Peñarrubia','Pidigan','Pilar','Sallapadan','San Isidro',
                    'San Juan','San Quintin','Tayum','Tineg','Tubo','Villaviciosa',
                ],
            ],
            'Apayao' => [
                'region' => 'CAR',
                'cities' => [],
                'municipalities' => [
                    'Calanasan','Conner','Flora','Kabugao','Luna','Pudtol','Santa Marcela',
                ],
            ],
            'Benguet' => [
                'region' => 'CAR',
                'cities' => ['Baguio'],
                'municipalities' => [
                    'Atok','Bakun','Bokod','Buguias','Itogon','Kabayan','Kapangan',
                    'Kibungan','La Trinidad','Mankayan','Sablan','Tuba','Tublay',
                ],
            ],
            'Ifugao' => [
                'region' => 'CAR',
                'cities' => [],
                'municipalities' => [
                    'Aguinaldo','Alfonso Lista','Asipulo','Banaue','Hingyon','Hungduan',
                    'Kiangan','Lagawe','Lamut','Mayoyao','Tinoc',
                ],
            ],
            'Kalinga' => [
                'region' => 'CAR',
                'cities' => [],
                'municipalities' => [
                    'Balbalan','Lubuagan','Pasil','Pinukpuk','Rizal','Tabuk',
                    'Tanudan','Tinglayan',
                ],
            ],
            'Mountain Province' => [
                'region' => 'CAR',
                'cities' => [],
                'municipalities' => [
                    'Barlig','Bauko','Besao','Bontoc','Natonin','Paracelis',
                    'Sabangan','Sadanga','Sagada','Tadian',
                ],
            ],

            // ══ BARMM — BANGSAMORO AUTONOMOUS REGION ══════════════════
            'Basilan' => [
                'region' => 'BARMM',
                'cities' => ['Lamitan'],
                'municipalities' => [
                    'Akbar','Al-Barka','Hadji Mohammad Ajul','Hadji Muhtamad',
                    'Isabela','Maluso','Sumisip','Tabuan-Lasa','Tipo-Tipo',
                    'Tuburan','Ungkaya Pukan',
                ],
            ],
            'Lanao del Sur' => [
                'region' => 'BARMM',
                'cities' => ['Marawi'],
                'municipalities' => [
                    'Bacolod-Kalawi','Balabagan','Balindong','Bayang','Binidayan',
                    'Buadiposo-Buntong','Bubong','Bumbaran','Butig','Calanogas',
                    'Capai','Ditsaan-Ramain','Ganassi','Lumba-Bayabao','Lumbaca-Unayan',
                    'Lumbatan','Lumbayanague','Madalum','Madamba','Maguing','Malabang',
                    'Marantao','Marogong','Masiu','Mulondo','Pagayawan','Piagapo',
                    'Picong','Poona Bayabao','Pualas','Saguiaran','Sultan Dumalondong',
                    'Sultan Gumander','Tagoloan II','Tamparan','Taraka','Tubaran',
                    'Tugaya','Wao',
                ],
            ],
            'Maguindanao del Norte' => [
                'region' => 'BARMM',
                'cities' => ['Cotabato'],
                'municipalities' => [
                    'Barira','Buldon','Datu Blah T. Sinsuat','Datu Odin Sinsuat',
                    'Kabuntalan','Matanog','Northern Kabuntalan','Parang','Sultan Kudarat',
                    'Sultan Mastura','Upi',
                ],
            ],
            'Maguindanao del Sur' => [
                'region' => 'BARMM',
                'cities' => [],
                'municipalities' => [
                    'Ampatuan','Buluan','Datu Abdullah Sangki','Datu Anggal Midtimbang',
                    'Datu Hoffer Ampatuan','Datu Paglas','Datu Piang','Datu Salibo',
                    'Datu Saudi-Ampatuan','Datu Unsay','General Salipada K. Pendatun',
                    'Guindulungan','Mamasapano','Mangudadatu','Pagalungan','Paglat',
                    'Pandag','Rajah Buayan','Shariff Aguak','Shariff Saydona Mustapha',
                    'South Upi','Sultan sa Barongis','Talayan','Talitay',
                ],
            ],
            'Sulu' => [
                'region' => 'BARMM',
                'cities' => [],
                'municipalities' => [
                    'Hadji Panglima Tahil','Indanan','Jolo','Kalingalan Caluang',
                    'Lugus','Luuk','Maimbung','Old Panamao','Omar','Pandami',
                    'Panglima Estino','Pangutaran','Parang','Pata','Patikul',
                    'Siasi','Talipao','Tapul',
                ],
            ],
            'Tawi-Tawi' => [
                'region' => 'BARMM',
                'cities' => [],
                'municipalities' => [
                    'Bongao','Languyan','Mapun','Panglima Sugala','Sapa-Sapa',
                    'Sibutu','Simunul','Sitangkai','South Ubian','Tandubas','Turtle Islands',
                ],
            ],
        ];
    }
}
