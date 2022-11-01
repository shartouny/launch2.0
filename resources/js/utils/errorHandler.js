import { message } from 'antd';

/**
 *
 * @param {{}} error
 * @param {number} duration
 */
export const displayErrors = (error, duration = 5) => {

  if (error && error.response && error.response.data) {
    if (error.response.data.errors) {

      let errorMessage = typeof error.response.data.errors == 'string'
        ? error.response.data.errors
        : error.response.data.errors[0]
        ?? error.response.data.errors[Object.keys(error.response.data.errors)[0]];

      if (Array.isArray(errorMessage) && errorMessage[0]) {
        errorMessage = errorMessage[0];
      } else if (typeof errorMessage === 'object') {
        let explodedMessage = '';
        for (let prop in errorMessage) {
          for (let i = 0; i < errorMessage[prop].length; i++) {
            explodedMessage += `${errorMessage[prop][i]} `;
          }
          explodedMessage += "\r\n";
        }
        errorMessage = explodedMessage;
      }
      return message.error(errorMessage, duration);
    }

    /**
     * 400s caused an error to come back as a reason from the data string.
     * error responses sometimes don't have messages on the data object not sure
     * if this is specific to the BE framework but this is how error responses
     * work in js.
     */
    if (typeof error.response.data === 'string') {
      return message.error(error.response.data, duration);
    }

    if (error.response.data.message) {
      if(error.response.data.exception === 'ErrorException' || error.response.data.exception === 'Exception'){
        return message.error('Server Error, please try again later', duration);
      }
      return message.error(error.response.data.message, duration);
    }
  }

  if (typeof error === 'string') {
    return message.error(error, duration);
  }

  console.error(error);
  message.error('Server Error, please try again later', duration);
};
