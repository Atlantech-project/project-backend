<?php

// P1-A3 step 7 gate proof. This test fails on purpose so the required checks go red.
// It must NEVER be merged: close the demo PR and delete the branch afterwards.
test('CI gate demo: a failing test blocks the pull request', function () {
    expect(true)->toBeFalse();
});
