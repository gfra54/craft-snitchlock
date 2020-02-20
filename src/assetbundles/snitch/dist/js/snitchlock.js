 /**
 * SnitchLock plugin for Craft CMS
 *
 * SnitchLock JS
 *
 * @author    Gilles FRANCOIS
 * @link      https://sopress.net
 * @package   SnitchLock
 * @since     1.0.0
 */
/*
This javascript file is injected on every back-end page. Here is what it does:
1) Fetch the snitchlock config values, store them as globals
2) Look for edit forms on this page - poll, because we want to find any modal edit windows that may appear.
For each edit form, we add the <div class="snitchlock"></div> warning container where any warnings will be displayed,
and start polling the server to look for conflicts.
When conflicts are reported, they are passed to warn(), which collects the current warnings from the warning
container (indexed by email address), and adds any new ones. Warnings are never removed, because a change
might have been made even if the conflicting editor is no longer editing.
When the close button on a warning is clicked, the warning class is changed, and css will hide the warning.
 */

$(function () { // for namespacing if nothing else...

// unchanging values from the config file
var globalPollInterval = null;
var globalMessage = null;
var globalElementInputIdSelector = null;
var globalFieldInputIdSelector = null;

// queue of collision searches https://www.i-programmer.info/programming/jquery/10443-jquery-3-function-queues.html
// we have a queue of ajax calls here. The last element on the queue is a no-op, so we can
// safely tell if the queue is empty by removing it (and if there was no last entry it was empty)
// the function passed to addToQueue will have the necessary context bound to it explicitly. That
// function does its thing, then adds a new iteration of itself to the queue, then waits, then
// calls the next in the queue.
var $g_postQueue = $({});

var addToQueue = function(queueFunction) {
  var state = $g_postQueue.queue('snitchlock').pop(); // either marker no-op, or queue is empty
  $g_postQueue.queue('snitchlock', queueFunction); // add ours
  $g_postQueue.queue('snitchlock', function(){}); // add back the no-op
  if (state === undefined) {
    $g_postQueue.dequeue('snitchlock'); // restart if queue was empty
  }
};

var currentWarnings = function($warnContainer) {
  var $warnings = $warnContainer.children('div');
  var ret = {};

  $warnings.each(function() {
    var email = $(this).data('email');
    ret[email] = true;
  });
  return ret;
};

var warn = function(elementId, $warnContainer, collisions) {
  // get the old warnings
  var oldWarnings = currentWarnings($warnContainer);

  // each person in new warnings:
  for (var i=0; i < collisions.length; i++) {
    var email = collisions[i].email;
    // if in old warnings already, ignore
    if (!oldWarnings[email]) {
      // otherwise add to container
      $warnContainer.append('<div data-email="'+email+'"><div>'+collisions[i].message+'</div></div>');
    }
  }
};

// our close button click handler
$('body').on('click', '.snitchlock span', function() {
  var $div = $(this).closest('div[data-email]');
  // hide it with css
  $div.addClass('snitchlock--hidden');
});


// look for conflicts, then add ourselves to the queue, then wait, then dequeue
var lookForConflicts = function(next) {
  var myThis = this;
  var $warnContainer = this.isModal ? this.$form.children('.snitchlock--modal') : $('.snitchlock--main');
  if (this.isModal && !this.$form.closest('.hud.has-footer[style*="display: block;"]').length) {
    // our modal is gone.
    next();
  } else {
      Craft.postActionRequest(
        'snitchlock/collision/ajax-enter',
        {
          snitchlockId: this.snitchlockId,
          snitchlockType: this.snitchlockType,
          messageTemplate: globalMessage
        },
        function(response, textStatus) {
          if (textStatus == 'success') {
            if (response && response['collisions'].length) {
              warn(myThis.snitchlockId, $warnContainer, response['collisions']);
            }
            addToQueue(lookForConflicts.bind(myThis));
            window.setTimeout(next, globalPollInterval);
          } else {
            next(); // trouble with this call, don't try it again.
          }
        }
      );
    }
};

var lookForEditFormsByType = function(snitchlockType, selector) {
  // find all the hidden id input fields on the page (in main form and any modal forms)
  var $idInputs = $(selector);

  $idInputs.each(function() {
    var $thisIdInput = $(this);
    var snitchlockId = $thisIdInput.val();
    var $form = $thisIdInput.closest('form');
    var isModal = $form.hasClass('body');
    var snitchlockData = $form.data('snitchlock');

    if (!snitchlockData) {
      // start looking for conflicts for this thing. Add the div for results,
      // look once, and arrange to poll
      if (isModal) {
        $form.prepend('<div class="snitchlock snitchlock--modal"></div>');
      } else {
        $('#main-container').prepend('<div class="snitchlock snitchlock--main"></div>');
      }
      $form.data('snitchlock', snitchlockId);

      // push our bound lookForConflicts on the queue, which will start queue if needed
      addToQueue(lookForConflicts.bind({
        $form: $form,
        snitchlockId: snitchlockId,
        snitchlockType: snitchlockType,
        isModal: isModal
      }));
    }
  });
};

var lookForEditForms = function() {
  if (globalElementInputIdSelector) {
    lookForEditFormsByType('element', globalElementInputIdSelector);
  }
  if (globalFieldInputIdSelector) {
    lookForEditFormsByType('field', globalFieldInputIdSelector);
  }
};

// get our configuration, and once we have that, start polling for edit forms
var doEverything = function() {
  Craft.postActionRequest(
      'snitchlock/collision/get-config',
      {},
      function(response, textStatus) {
        if (textStatus == 'success' && response) {
          globalPollInterval = response['serverPollInterval'] * 1000;
          globalMessage = response['messageTemplate'];
          globalElementInputIdSelector = response['elementInputIdSelector'];
          globalFieldInputIdSelector = response['fieldInputIdSelector'];
          lookForEditForms();
          window.setInterval(lookForEditForms, globalPollInterval);
        }
      }
  );
};

doEverything();
});

