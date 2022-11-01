/**
 * Local storage util
 */
export default {
  /**
   *
   * @param {string} key
   * @param {string} value
   */
  save(key, value) {
    window.localStorage.setItem(key, value.toString());
  },
  /**
   *
   * @param {string} key
   */
  delete(key) {
    window.localStorage.removeItem(key);
  },
  /**
   *
   * @param {string} key
   * @returns {string}
   */
  get(key) {
    return window.localStorage.getItem(key)
  }
}
