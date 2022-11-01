import React, { cloneElement, Component } from 'react';

class Recaptcha extends Component {
  constructor(props) {
    super(props);
    const recaptchaSiteKey = process.env.MIX_RECAPTCHA_PUBLIC_KEY;
    this.state = {
      recaptchaSiteKey,
      recaptchaToken: null
    };

    if(recaptchaSiteKey) {
      const script = document.createElement("script");
      script.src = `https://www.google.com/recaptcha/api.js?render=${recaptchaSiteKey}`;
      document.body.appendChild(script);
    } else {
      console.warn('recaptcha disabled');
    }
  }

  getRecaptchaToken = async () => {
    const { recaptchaSiteKey } = this.state;
    let token = null;
    await window.grecaptcha.execute(recaptchaSiteKey, { action: "password_reset" })
      .then(recaptchaToken => {
        token = recaptchaToken;
        this.setState({recaptchaToken:recaptchaToken})
      });
    return token;
  };

  render() {
    const { recaptchaSiteKey, recaptchaToken } = this.state;

    if(!recaptchaSiteKey){
      return <>{this.props.children}</>;
    }

    return (
      <>
        {cloneElement(this.props.children, {
          getRecaptchaToken: this.getRecaptchaToken,
          recaptchaToken: recaptchaToken
        })}
        <div
          className="g-recaptcha"
          data-sitekey={recaptchaSiteKey}
          data-size="invisible"
        />
      </>
    )
  }
}

export default Recaptcha;
