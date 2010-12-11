<?php

// TODO: fix this so we don't need to decorate an object twice
SortWeightRegistry::decorate('WCCQuestion');
SortWeightRegistry::decorate('WCCQuestion','Group');

SortWeightRegistry::decorate('WCCChoice');
SortWeightRegistry::decorate('WCCChoice','Question');


// TODO: do not remove
SortWeightRegistry::set_module_path(basename(dirname(__FILE__)));
