import {createBehavior} from '@area17/a17-behaviors';

const checkableList = createBehavior('checkableList',
    {

    },
    {
        init() {
            console.log('checkableList', this.$node);
        }
    }
);

export default checkableList;