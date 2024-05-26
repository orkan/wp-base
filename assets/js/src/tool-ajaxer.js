/* {FILE: _fn.js} */
/* {FILE: _ajax.js} */

(function ($) {
  /*
   * ---------------------------------------------------------------------------------------------------------
   * onLoad
   */
  $(() => {
    let Controller;
    // [selector] Handle onChange event on <select>
    $($plu.fn.getId('selector_select'))
      .on('change', async (Event) => {
        const name = Event.currentTarget.value;
        const Result = $plu.fn.getElement('selector_result');
        Result.value = '---';

        if (name) {
          try {
            // Abort previous Request if in progress (race condition)
            Controller && Controller.abort();
            Controller = new AbortController();

            // Ajax request
            Result.value = $plu.l10n.wait;
            Result.value = await $plu.Ajax.fetch(
              {
                action: $plu.actions.ajaxer.selector,
                signal: Controller.signal,
                Spinner: document.getElementById('selector_spin'),
              },
              { name }
            );

            Controller = null;
          } catch (E) {
            Result.value = 'Error: ' + E.message;
            throw E;
          }
        }
      })
      .change();
  });

  $(() => {
    let Controller;
    // [texter] Handle onInput event on <text>
    $($plu.fn.getId('texter_text')).on('input', async (Event) => {
      const text = Event.currentTarget.value;
      const error = $plu.fn.getElement('texter_error').checked;
      const Result = $plu.fn.getElement('texter_result');
      try {
        // Abort previous Request if in progress (race condition)
        Controller && Controller.abort();
        Controller = new AbortController();

        // Ajax request
        Result.value = await $plu.Ajax.fetch(
          {
            action: $plu.actions.ajaxer.echotext,
            signal: Controller.signal,
            Spinner: document.getElementById('texter_spin'),
          },
          { text, error }
        );

        Controller = null;
      } catch (E) {
        Result.value = 'Error: ' + E.message;
        throw E;
      }
    });
  });

  $(() => {
    // [cache] Clear input fields cache
    $($plu.fn.getId('cache_btn')).on('click', async (Event) => {
      const Div = document.getElementById('cache_result');
      try {
        Event.currentTarget.disabled = true;
        Div.innerHTML = '';
        Div.innerHTML = await $plu.Ajax.fetch({
          action: $plu.actions.ajaxer.flushcache,
          method: 'GET',
          Spinner: document.getElementById('cache_spin'),
        });
        Event.currentTarget.disabled = false;
      } catch (E) {
        Div.innerHTML = 'Error: ' + E.message;
        throw E;
      } finally {
        Event.currentTarget.disabled = false;
      }
    });
  });
})(jQuery);
