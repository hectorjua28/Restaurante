<?php

namespace Database\Seeders;

use App\Enums\Station;
use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // [categoría => [[nombre, descripción, precio en pesos, estación, [modificador => extra en pesos]]]]
        $menu = [
            'Antojitos' => [
                ['Bocoles con frijol', 'Tres bocoles de masa con manteca rellenos de frijol negro.', 45, Station::Cocina, ['Con queso' => 10, 'Sin salsa' => 0]],
                ['Bocoles con chicharrón', 'Tres bocoles rellenos de chicharrón prensado en salsa verde.', 55, Station::Cocina, ['Con queso' => 10, 'Sin salsa' => 0]],
                ['Picadas', 'Cuatro picadas con salsa roja o verde, cebolla y queso.', 60, Station::Cocina, ['Salsa roja' => 0, 'Salsa verde' => 0, 'Sin cebolla' => 0, 'Con huevo' => 15]],
                ['Gorditas de frijol', 'Tres gorditas de masa rellenas de frijol con salsa.', 50, Station::Cocina, ['Sin cebolla' => 0]],
                ['Tamal de cazuela', 'Tamal tradicional de la Huasteca con carne de cerdo.', 40, Station::Cocina, []],
            ],
            'Enchiladas y platos fuertes' => [
                ['Enchiladas verdes', 'Cuatro enchiladas con salsa verde, crema, queso y cebolla.', 85, Station::Cocina, ['De pollo' => 15, 'Sin cebolla' => 0, 'Sin crema' => 0]],
                ['Enchiladas de mole', 'Cuatro enchiladas bañadas en mole con ajonjolí.', 95, Station::Cocina, ['De pollo' => 15, 'Sin ajonjolí' => 0]],
                ['Enfrijoladas', 'Cuatro tortillas bañadas en frijol con queso y crema.', 75, Station::Cocina, ['Con huevo' => 15, 'Con cecina' => 30]],
                ['Cecina con enchiladas', 'Cecina asada acompañada de enchiladas, frijoles y ensalada.', 140, Station::Cocina, ['Término medio' => 0, 'Bien cocida' => 0]],
                ['Zacahuil (porción)', 'Porción de zacahuil con salsa y tortillas.', 70, Station::Cocina, []],
            ],
            'Bebidas calientes' => [
                ['Café de olla', 'Café con piloncillo y canela.', 30, Station::Barra, ['Sin azúcar' => 0, 'Con leche' => 5]],
                ['Chocolate de agua', 'Chocolate tradicional batido.', 35, Station::Barra, ['Con leche' => 5]],
                ['Champurrado', 'Atole de masa con chocolate.', 35, Station::Barra, []],
            ],
            'Aguas frescas' => [
                ['Agua de horchata', 'Vaso de 500 ml.', 30, Station::Barra, ['Sin hielo' => 0, 'Litro' => 20]],
                ['Agua de jamaica', 'Vaso de 500 ml.', 30, Station::Barra, ['Sin hielo' => 0, 'Litro' => 20]],
                ['Agua de vainilla de Papantla', 'Vaso de 500 ml con vainilla de la región.', 35, Station::Barra, ['Sin hielo' => 0, 'Litro' => 25]],
                ['Refresco', 'Botella de vidrio 355 ml.', 28, Station::Barra, []],
            ],
            'Postres' => [
                ['Pan de elote', 'Rebanada de pan de elote casero.', 40, Station::Cocina, ['Con lechera' => 8]],
                ['Plátanos fritos', 'Con crema y azúcar.', 45, Station::Cocina, ['Sin crema' => 0]],
            ],
        ];

        $position = 1;
        foreach ($menu as $categoryName => $products) {
            $category = Category::updateOrCreate(['name' => $categoryName], ['position' => $position++, 'active' => true]);

            foreach ($products as [$name, $description, $price, $station, $modifiers]) {
                $product = $category->products()->updateOrCreate(['name' => $name], [
                    'description' => $description,
                    'price_cents' => $price * 100,
                    'station' => $station,
                    'available' => true,
                ]);

                foreach ($modifiers as $modName => $extra) {
                    $product->modifiers()->updateOrCreate(['name' => $modName], ['price_delta_cents' => $extra * 100]);
                }
            }
        }
    }
}
