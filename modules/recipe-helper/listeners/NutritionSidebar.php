<?php

namespace MattBloomfield\RecipeHelper\listeners;

use CraftCms\Cms\Element\Events\ElementEditorPayloadResolving;
use CraftCms\Cms\Element\Events\ElementSidebarHtmlResolving;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\TemplateContent;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\View\TemplateMode;

use function CraftCms\Cms\template;

/**
 * Shows the recipe's calories per serving in the entry editor sidebar, with a
 * way to force nutrition to be recalculated on the next save.
 *
 * The full-page editor builds its sidebar as a Ui, so the nutrition nodes are
 * appended to that payload. Slideouts still render sidebar HTML, which gets
 * the original template (with its "Force Recalculate" button).
 */
class NutritionSidebar
{
    public static function addToEditor(ElementEditorPayloadResolving $event): void
    {
        $entry = $event->element;

        if (!self::isRecipe($entry)) {
            return;
        }

        $sidebar = $event->data['sidebarUi'] ?? null;
        $readOnly = (bool)($event->data['readOnly'] ?? false);
        $context = new UiContext(mode: $readOnly ? ControlMode::ReadOnly : ControlMode::Editable);

        $nutrition = app(UiResolver::class)->resolve(Ui::make([
            TemplateContent::make(
                'recipe-helper-nutrition',
                template('recipe-helper/nutrition/_summary', ['entry' => $entry], TemplateMode::Cp),
            )->trusted(),
            Field::make('Force Recalculate')
                ->instructions('Recalculate nutrition on the next save, even if the ingredients haven’t changed.')
                ->control(Lightswitch::make('forceNutrition')->value(false)),
        ]), $context);

        $event->data['sidebarUi'] = $sidebar instanceof UiPayload
            ? new UiPayload(
                scope: $sidebar->scope,
                refreshable: $sidebar->refreshable,
                nodes: [...$sidebar->nodes, ...$nutrition->nodes],
                values: [...$sidebar->values, ...$nutrition->values],
                errors: $sidebar->errors,
                globalErrors: $sidebar->globalErrors,
            )
            : $nutrition;
    }

    public static function addToSidebarHtml(ElementSidebarHtmlResolving $event): void
    {
        if (!self::isRecipe($event->element)) {
            return;
        }

        $event->html .= template('recipe-helper/nutrition/_sidebar', ['entry' => $event->element], TemplateMode::Cp);
    }

    private static function isRecipe(mixed $element): bool
    {
        return $element instanceof Entry && $element->getType()->handle === 'recipe';
    }
}
