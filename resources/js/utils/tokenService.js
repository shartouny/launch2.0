const CryptoJS = require('crypto-js');
const key = 'gafKm+g710YAM7kgGkT9ddoKzgpcAKGeIb5PbcfvJNw=';

export default {
  /**
   *
   * @param {string} token
   */
  saveToken(token) {
    const cipherText = CryptoJS.AES.encrypt(token, key);
    window.localStorage.setItem('api_token', cipherText.toString());
  },
  /**
   *
   */
  deleteToken() {
    window.localStorage.removeItem('api_token');
    window.localStorage.removeItem('is_verified');
  },
  /**
   *
   * @returns {string|boolean}
   */
  getToken() {
    const cipherText = window.localStorage.getItem('api_token');

    if (!cipherText) {
      return false;
    }

    return CryptoJS.AES
      .decrypt(cipherText, key)
      .toString(CryptoJS.enc.Utf8);
  },
  /**
   *
   * @returns {string}
   */
  isLoggedIn() {
    return window.localStorage.getItem('api_token');
  },

  /**
   *
   * @param {bool|null} emailVerifiedAt
   */
  saveIsVerified(emailVerifiedAt) {
    window.localStorage.setItem('is_verified', emailVerifiedAt === true);
  },

  /**
   *
   * @return {boolean}
   */
  getIsVerified(){
    return window.localStorage.getItem('is_verified') === 'true';
  }
}

