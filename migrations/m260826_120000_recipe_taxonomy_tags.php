<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;

/**
 * Applies the LLM-generated faceted category tags (produced on local via
 * recipe-helper/categorize/run) to recipes by matching slugs. Additive: adds
 * mapped categories to each recipe's existing set, never removes. Idempotent —
 * re-running adds nothing new. Recipes not present in the map (or on this
 * environment) are skipped.
 */
class m260826_120000_recipe_taxonomy_tags extends Migration
{
    public function safeUp(): bool
    {
        $map = json_decode(self::DATA, true);

        $catId = [];
        foreach (Entry::find()->section('categories')->status(null)->all() as $c) {
            if ($c->slug) {
                $catId[$c->slug] = (int)$c->id;
            }
        }

        $tagged = 0;
        $skipped = 0;

        foreach ($map as $recipeSlug => $catSlugs) {
            $recipe = Entry::find()->section('recipes')->slug($recipeSlug)->status(null)->one();
            if (!$recipe) {
                echo "    skip (not found): {$recipeSlug}\n";
                $skipped++;
                continue;
            }

            $existing = array_map('intval', ArrayHelper::getColumn($recipe->getFieldValue('categories')->all(), 'id'));
            $add = [];
            foreach ($catSlugs as $slug) {
                if (isset($catId[$slug])) {
                    $add[] = $catId[$slug];
                }
            }

            $final = array_values(array_unique(array_merge($existing, $add)));
            if (count($final) === count($existing)) {
                continue; // nothing new
            }

            $recipe->setFieldValue('categories', $final);
            if (Craft::$app->getElements()->saveElement($recipe)) {
                $tagged++;
            } else {
                echo "    save failed ({$recipeSlug}): " . implode(', ', $recipe->getFirstErrors()) . "\n";
                $skipped++;
            }
        }

        echo "    recipe taxonomy tags: updated {$tagged}, skipped {$skipped}\n";
        return true;
    }

    public function safeDown(): bool
    {
        echo "m260826_120000_recipe_taxonomy_tags cannot be reverted.\n";
        return false;
    }

    private const DATA = <<<'JSON'
{"7-layer-bean-dip":["appetizers-snacks","dips","mexican","vegetarian"],"apple-brie-crostini-with-hot-honey":["appetizers-snacks","main-dish","pork"],"apple-cider-doughnut-loaf-cake":["cakes","desserts","loaves","vegetarian"],"apple-dip":["appetizers-snacks","desserts","dips","side-dish","vegetarian"],"aunt-opals-banana-pudding":["desserts","vegetarian"],"award-winning-cheeseburger-soup":["beef","main-dish","soup-and-chili"],"award-winning-zucchini-bread":["bread","desserts","loaves","vegetarian"],"baked-croissant-french-toast-with-lemon-cream-cheese":["breakfast","vegetarian"],"bang-bang-chicken-rice-paper-rolls":["appetizers-snacks","asian","chicken","main-dish"],"basic-crepes":["breakfast","vegetarian"],"berry-crisp":["crisps-cobblers","desserts","vegetarian"],"better-than-anything-cake":["cakes","desserts","vegetarian"],"big-soft-oatmeal-cookies":["cookies-bars","desserts","vegetarian"],"biscuits":["bread","breakfast","vegetarian"],"browned-butter-jalape\u00f1o-cornbread-muffins":["bread","breakfast","muffins","side-dish","vegetarian"],"brownie-pudding":["desserts","vegetarian"],"brownies":["cookies-bars","desserts","vegetarian"],"bursting-blueberry-lemon-cake":["cakes","desserts","vegetarian"],"buttermilk-syrup":["breakfast","dips","vegetarian"],"butternut-squash-gnocchi-italian-sausage":["main-dish","pasta","pork"],"carrot-cake-baked-oats":["breakfast","vegetarian"],"chia-pudding":["breakfast","desserts","side-dish","vegetarian"],"chicken-pot-pie":["chicken","main-dish","pies"],"chicken-salad-croissant-sandwiches":["chicken","main-dish","sandwiches"],"chicken-tortilla-soup":["chicken","mexican","soup-and-chili"],"chocolate-buttercream-frosting":["desserts"],"chocolate-fudge-cake":["cakes","desserts","vegetarian"],"chocolate-lasagna":["cakes","desserts","vegetarian"],"chocolate-sourdough-bread":["bread","desserts","main-dish","vegetarian"],"chocolate-sourdough-bread-2":["bread","desserts","main-dish"],"christmas-cinnamon-bread":["bread","christmas","desserts","loaves","vegetarian"],"cinnamon-rolls":["bread","breakfast","desserts","sweet-rolls","vegetarian"],"classic-st-lucia-buns-lussekatter":["bread","christmas","desserts","rolls","swedish","sweet-rolls"],"copycat-panera-broccoli-cheddar-soup":["soup-and-chili"],"crack-chicken-noodle-soup":["chicken","main-dish","soup-and-chili"],"creamed-corn":["side-dish","vegetarian"],"creamy-apple-slaw":["salads","side-dish","vegetarian"],"creamy-apple-slaw-recipe":["salads","side-dish","vegetarian"],"creamy-butternut-squash-gnocchi-with-sausage-thyme-and-sage":["main-dish","pasta","pork"],"creamy-chicken-and-corn-chowder":["chicken","main-dish","soup-and-chili"],"creamy-chicken-enchilada-soup":["chicken","main-dish","mexican","soup-and-chili"],"creamy-chicken-pasta-with-cajun-sauce":["chicken","main-dish","pasta"],"creamy-corn-garlic-chicken-with-cheesy-polenta":["chicken","main-dish"],"creamy-gnocchi-skillet-recipe":["chicken","main-dish"],"creamy-italian-sausage-rigatoni":["main-dish","pasta","pork"],"creamy-parmesan-chicken-penne":["chicken","main-dish","pasta"],"creamy-parmesan-italian-sausage-soup":["main-dish","pasta","pork","soup-and-chili"],"creamy-parmesan-one-pot-chicken-and-rice":["chicken","main-dish"],"creamy-smothered-chicken-and-rice-recipe":["chicken","main-dish"],"creamy-white-chicken-chili":["chicken","main-dish","soup-and-chili"],"crockpot-bbq-chicken":["chicken","main-dish","slow-cooker"],"crockpot-beef-stroganoff":["beef","main-dish","pasta","slow-cooker"],"crockpot-chicken-tacos":["chicken","main-dish","mexican","slow-cooker"],"crockpot-coconut-chicken-curry-with-crispy-shallot-basil-oil":["asian","chicken","main-dish","slow-cooker"],"crockpot-creamy-broccoli-cheddar-chicken":["chicken","instant-pot","main-dish","slow-cooker"],"crockpot-honey-garlic-chicken-and-broccoli":["chicken","main-dish","slow-cooker"],"crockpot-honey-garlic-chicken-broccoli":["asian","chicken","main-dish","slow-cooker"],"dutch-babies":["breakfast","vegetarian"],"dutch-jan-hagel-cookies":["cookies-bars","desserts","vegetarian"],"dutch-oven-lasagna":["beef","main-dish","pasta"],"easy-dutch-apple-pie":["desserts","pies","vegetarian"],"easy-orange-cranberry-sauce":["side-dish","thanksgiving","vegetarian"],"easy-slow-cooker-texas-bbq-pulled-pork":["main-dish","pork","sandwiches","slow-cooker"],"easy-white-chicken-chili-corn-dip":["appetizers-snacks","chicken","dips","main-dish"],"fresh-mango-salsa":["appetizers-snacks","dips","side-dish","vegetarian"],"german-oven-pancakes":["breakfast","vegetarian"],"giant-frosted-strawberry-pop-tart":["breakfast","desserts","vegetarian"],"grandmas-chocolate-chip-cookies":["cookies-bars","desserts","vegetarian"],"grandpa-johns-pumpkin-cake":["cakes","desserts","vegetarian"],"grandpa-rays-rolls":["bread","rolls","side-dish","vegetarian"],"grannys-monkey-bread":["bread","breakfast","desserts"],"hamburger-buns":["appetizers-snacks","bread","vegetarian"],"hawaiian-chicken-tacos-with-jalapeno-ranch-slaw":["chicken","main-dish","mexican"],"healthy-applesauce-oat-muffins":["breakfast","muffins","vegetarian"],"homemade-bread":["bread","breakfast","loaves","main-dish","vegetarian"],"homemade-bread-bowls":["bread","loaves","side-dish","soup-and-chili","vegetarian"],"homemade-coconut-cream-pie":["desserts","pies","vegetarian"],"homemade-ice-cream":["desserts","vegetarian"],"honey-apple-cheddar-and-bacon-panini":["main-dish","pork","sandwiches"],"honey-mustard-chicken":["chicken","main-dish"],"hummus-bowls-with-easy-chicken-shawarma":["chicken","main-dish"],"individual-sticky-toffee-puddings":["cakes","desserts","vegetarian"],"instant-pot-chicken-thighs-honey-garlic-sauce":["asian","chicken","instant-pot","main-dish"],"jennis-frittata-quiche":["breakfast","main-dish","pork","vegetarian"],"jiffy-corncake":["bread","side-dish","vegetarian"],"jumbo-chocolate-chip-muffins":["breakfast","desserts","muffins","vegetarian"],"kladdkaka":["cakes","desserts","swedish","vegetarian"],"kona-banana-bread":["bread","breakfast","desserts","loaves","vegetarian"],"lailas-crunchy-chicken-salad":["chicken","main-dish","salads"],"lemon-cream-cookies":["cookies-bars","desserts","vegetarian"],"linseys-cheeseball":["appetizers-snacks","vegetarian"],"maple-syrup":["dips","side-dish","vegetarian"],"melt-in-your-mouth-pumpkin-cookies":["cookies-bars","desserts","vegetarian"],"mexican-street-corn-creamed-corn":["mexican","side-dish","vegetarian"],"mina-b\u00e4sta-k\u00f6ttbullar":["beef","main-dish","pork","swedish"],"minas-lemon-bars":["cookies-bars","desserts","vegetarian"],"mongolian-ground-beef-noodles":["asian","beef","main-dish","pasta"],"monster-cookies":["cookies-bars","desserts","vegetarian"],"monte-cristos":["main-dish","pork","sandwiches"],"neapolitan-four-cheese-pizza":["main-dish","pizza","vegetarian"],"neiman-marcus-bars":["cookies-bars","desserts","vegetarian"],"no-bake-energy-bites":["appetizers-snacks","vegetarian"],"no-fail-homemade-pizza-sauce":["appetizers-snacks","dips","pizza","vegetarian"],"oatmeal-bar-recipe":["cookies-bars","desserts","vegetarian"],"oatmeal-bars":["appetizers-snacks","cookies-bars","desserts","vegetarian"],"oatmeal-cake":["cakes","desserts","vegetarian"],"oatmeal-cream-pie-cookies":["cookies-bars","desserts","vegetarian"],"paula-deens-baked-potato-soup":["soup-and-chili"],"pavlova":["desserts"],"peanut-butter-cup-cookies":["cookies-bars","desserts","vegetarian"],"peanut-butter-fudge":["cookies-bars","desserts","vegetarian"],"perfect-peach-crisp":["crisps-cobblers","desserts","vegetarian"],"philly-cheese-steak-pizza":["beef","main-dish","pizza"],"philly-cheesesteak-pasta-bowls":["beef","main-dish","pasta"],"potato-salad":["salads","side-dish","vegetarian"],"potato-sausage-chowder":["chicken","main-dish","pork","soup-and-chili"],"pretzel-salad":["desserts","side-dish","vegetarian"],"pretzels":["appetizers-snacks","bread","vegetarian"],"prosciutto-balsamic-peach-chicken-with-burrata-and-basil":["chicken","main-dish"],"pumpkin-chocolate-chip-cookies":["cookies-bars","desserts","side-dish","vegetarian"],"pumpkin-pie":["christmas","desserts","pies","thanksgiving","vegetarian"],"pumpkin-pie-crisp":["crisps-cobblers","desserts","vegetarian"],"pumpkin-sheet-cake-brown-butter-frosting":["cakes","desserts","thanksgiving","vegetarian"],"quick-and-easy-foolproof-pizza-dough":["bread","main-dish","pizza","vegetarian"],"quick-pickled-red-onions":["side-dish","vegetarian"],"raggedy-robins-gorilla-poops-no-bake-cookies":["cookies-bars","desserts","vegetarian"],"refried-bean-dip":["appetizers-snacks","dips","mexican","vegetarian"],"roasted-butternut-squash-prosciutto-pizza-with-caramelized-onions":["main-dish","pizza","pork","vegetarian"],"sage-butter-pumpkin-cheese-ravioli":["main-dish","pasta","vegetarian"],"salted-honey-butter-parker-house-rolls":["bread","christmas","rolls","side-dish","thanksgiving","vegetarian"],"sausage-gravy":["breakfast","main-dish","pork"],"shepherds-pie":["beef","main-dish"],"skillet-chicken-fajitas-jalapeno-verde-sauce":["chicken","main-dish","mexican"],"slow-cooker-bbq-ribs":["beef","main-dish","pork","slow-cooker"],"slow-cooker-chicken-bacon-ranch-sandwiches":["chicken","main-dish","sandwiches","slow-cooker"],"slow-cooker-chicken-bacon-ranch-sandwiches-2":["chicken","main-dish","sandwiches","slow-cooker"],"slow-cooker-pork-carnitas":["main-dish","mexican","pork","slow-cooker"],"soft-and-chewy-oatmeal-raisin-cookies":["cookies-bars","desserts","vegetarian"],"sourdough-breakfast-bars-jam":["breakfast","cookies-bars","desserts","vegetarian"],"sourdough-breakfast-bars-with-jam":["breakfast","cookies-bars","vegetarian"],"sourdough-granola-bars":["appetizers-snacks","cookies-bars","vegetarian"],"sourdough-granola-bars-2":["appetizers-snacks","cookies-bars","vegetarian"],"spiced-pear-cake-cardamom":["cakes","desserts","vegetarian"],"spinach-artichoke-dip":["appetizers-snacks","dips","vegetarian"],"spinach-dip-flatbread":["chicken","main-dish","pizza"],"st-lucia-buns":["breakfast","christmas","desserts","rolls","swedish","sweet-rolls","vegetarian"],"strawberry-cinnamon-bread":["bread","breakfast","desserts","loaves","sweet-rolls","vegetarian"],"stuffed-french-bread":["beef","bread","main-dish","sandwiches"],"sues-chili":["beef","main-dish","soup-and-chili"],"swedish-brunsas-brown-sauce":["dips","side-dish","swedish"],"sweet-and-spicy-beef-polenta":["beef","main-dish"],"sweet-potato-ham-and-corn-chowder":["main-dish","pork","soup-and-chili"],"sweet-potato-hash-with-sausage-and-eggs":["breakfast","main-dish","pork"],"sweet-potato-souffle":["side-dish","thanksgiving","vegetarian"],"texas-caviar-cowboy-caviar":["appetizers-snacks","dips","salads","side-dish","vegetarian"],"texas-sheet-cake":["cakes","desserts","vegetarian"],"thai-red-curry":["asian","chicken","main-dish"],"the-best-baked-ziti-with-cottage-cheese-postpartum-ziti":["main-dish","pasta","pork"],"the-best-baked-ziti-with-cottage-cheese-postpartum-ziti-2":["main-dish","pasta","pork"],"the-best-buttermilk-pancakes":["breakfast","vegetarian"],"the-ultimate-candy-corn-snack-mix":["appetizers-snacks","vegetarian"],"toaster-strudel":["breakfast","desserts","vegetarian"],"tortellini-spinach-bake-in-creamy-lemon-sauce":["main-dish","pasta","pork"],"true-belgian-waffles":["breakfast","vegetarian"],"vaniljs\u00e5s":["desserts","swedish"],"white-chicken-enchiladas":["chicken","main-dish","mexican"],"whole-grain-honey-bran-muffins":["breakfast","muffins","vegetarian"],"yardbird-carrot-cake":["cakes","desserts","vegetarian"]}
JSON;
}
