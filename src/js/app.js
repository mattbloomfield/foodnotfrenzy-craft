import "../css/app.css";

import { manageBehaviors } from '@area17/a17-behaviors';
import * as Behaviors from './_behaviors';

window.A17 = window.A17 || {};
window.A17.behaviors = manageBehaviors;
window.A17.behaviors.init(Behaviors, {
    breakpoints: []
});

if (import.meta.hot) {
    import.meta.hot.accept(() => {
        console.log('hot reload');
    });
}

