window.$plu = window.$plu || {};

/*
 * ==============================================================================================
 * Ork | Ajax
 */
$plu.Ajax = {
  /**
   * Ajax request
   */
  async fetch(opts = {}, data = {}) {
    let json,
      status,
      url = $plu.url;

    data.action = opts.action;
    data[$plu.nonce.name] = $plu.nonce.action;

    /*
     * Deaults:
     *
     * [signal]
     * See AbortController::signal
     *
     * [Spinner]
     * DOM element to toggle class from opts[spinCls]
     */
    opts = {
      ...{
        method: 'POST',
        signal: null,
        Spinner: null,
        spinCls: null,
      },
      ...opts,
    };

    switch (opts.method) {
      case 'GET':
        url += '?' + this.getHttpQuery(data);
        break;
      case 'POST':
        opts.body = this.getFormData(data);
        break;
    }

    this.showSpinner(true, opts.Spinner, opts.spinCls);

    const Response = await fetch(url, opts)
      .then((Response) => {
        status = `[${Response.status}] ${Response.statusText}`;
        $plu.fn.debug(Response);
        return Response.text();
      })
      .catch((E) => {
        throw new Error(E); // Connection errors
      });

    this.showSpinner(false, opts.Spinner, opts.spinCls);

    try {
      json = JSON.parse(Response);
    } catch (E) {
      throw new Error(E); // Data errors
    }

    if (!json || !json.success) {
      throw new Error(json.data || status); // WP errors
    }

    return json.data;
  },

  /**
   * Build query string from object.
   */
  getHttpQuery(obj) {
    const Query = new URLSearchParams(obj);
    return Query.toString();
  },

  /**
   * Convert Object to FormData instance.
   */
  getFormData(obj) {
    const Data = new FormData();
    for (const [name, value] of Object.entries(obj)) {
      Data.append(name, value);
    }
    return Data;
  },

  /**
   * Show/hide spinner
   */
  showSpinner(show, Spinner, className = null) {
    if (Spinner) {
      className = className ?? 'is-active';
      const ClassList = Spinner.classList;
      show ? ClassList.add(className) : ClassList.remove(className);
    }
  },
};
