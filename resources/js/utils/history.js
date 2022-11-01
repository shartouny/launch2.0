import localStorage from "./localStorage";

const CryptoJS = require('crypto-js');
const key = 'gafKm+g710YAM7kgGkT9ddoKzgpcAKGeIb5pbcfvJNw=';

/**
 * Name: saveVariantHistory
 * Role: cache pre-selected options in local-host
 * how it works:
 * 1. it will load previous histories
 * 2. it will add/update history base on blank id
 * @param selectedOptionValues example [options]
 */
export const saveVariantHistory = (selectedOptionValues) => {
  try {
    const data = localStorage.get('variants_history')
    let history = {};
    if (data){
      history = JSON.parse(CryptoJS.AES.decrypt(data, key).toString(CryptoJS.enc.Utf8));
    }
    Object.keys(selectedOptionValues).forEach(function (optionKey) {
      history[optionKey] = [];
      const selectedOptionsVariant = selectedOptionValues[optionKey]
      Object.keys(selectedOptionsVariant).forEach(function (key) {
        history[optionKey] = [...history[optionKey], ...selectedOptionsVariant[key]]
      })
    });
    localStorage.save('variants_history', CryptoJS.AES.encrypt(JSON.stringify(history), key));
  } catch (e) {
    console.log('error',e)
  }
}


/**
 * get variants pre-selected history
 * @returns {any}
 */
export const getVariantHistory = () => {
  try {
    const history = localStorage.get('variants_history')
    return (history) ? JSON.parse(CryptoJS.AES.decrypt(history, key).toString(CryptoJS.enc.Utf8)) : {};
  } catch (e) {
    console.log('error',e)
  }
}

