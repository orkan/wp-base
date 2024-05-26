window.$plu = window.$plu || {};

/*
 * ==============================================================================================
 * Ork | Functions
 */
$plu.fn = {
  /**
   * Get element ID from name.
   */
  getId(name) {
    return '#' + $plu.name2id[name];
  },

  /**
   * Get element from name.
   */
  getElement(name) {
    return document.getElementById($plu.name2id[name] ?? null);
  },

  /**
   * Logger.
   */
  log(msg, type = 'log') {
    if ('debug' === type && !$plu.debug) {
      return;
    } else {
      type = 'log';
    }

    console[type](msg);
  },

  debug(msg) {
    this.log(msg, 'debug');
  },

  /**
   * Clear FORM inputs.
   */
  clearForm(form) {
    form.reset();
    Array.from(form.elements).forEach((input) => {
      if (input.disabled) {
        return;
      }
      switch (input.type.toLowerCase()) {
        case 'text':
        case 'password':
        case 'textarea':
        case 'hidden':
          input.value = '';
          break;

        case 'radio':
        case 'checkbox':
          input.checked = false;
          break;

        case 'select-one':
        case 'select-multi':
          input.selectedIndex = -1;
          break;
      }
    });
  },

  /**
   * Must be called as: await usleep(1); from within another async function! :(
   */
  async usleep(usec) {
    return await new Promise((resolve, reject) => setTimeout(() => resolve(1), usec));
  },
};
