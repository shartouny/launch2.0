import React, {useEffect, useRef, useState} from "react"
import {Button, Col, Row} from "antd";
import {CardCvcElement, CardExpiryElement, CardNumberElement, useElements, useStripe} from "@stripe/react-stripe-js";

const StripeCheckoutForm = ({onSubmit}) => {
  const stripe = useStripe();
  const element = useElements()

  const [error, setError] = useState({
    cardNumber: false,
    expiry: false,
    cvv: false,
    isCardNumberComplete:false,
    isExpiryCompleted:false,
    isCVCComplete:false
  })


  const onCreditCardChange = (event,) => {

    if (event.error)
      setError({...error, cardNumber: true,isCardNumberComplete: event.complete})
    else if (!event.error)
      setError({...error, cardNumber: false,isCardNumberComplete: event.complete})
  }


  const onExpiryChange = (event) => {

    if (event.error )
      setError({...error, expiry: true,isExpiryCompleted: event.complete})
    else if (!event.error )
      setError({...error, expiry: false,isExpiryCompleted: event.complete})
  }

  const onCVVChange = (event) => {

    if (event.error )
      setError({...error, cvv: true,isCVCComplete: event.complete})
    else if (!event.error )
      setError({...error, cvv: false,isCVCComplete: event.complete})
  }

  const [isLoading, setIsLoading] = useState()

  const onClickPressed = () => {

    if(error.isCardNumberComplete && error.isExpiryCompleted && error.isExpiryCompleted)
    {
      onSubmit(stripe,element.getElement(CardCvcElement))
      setIsLoading(true)
    }
  }

  return <div>
    <Row style={{marginTop: 10, marginBottom: 10}}>
      <CardNumberElement onChange={onCreditCardChange} options={styles.cardNumberOptions}></CardNumberElement>
    </Row>
    <Row>
      <Col span={12}>
        <div style={{marginRight: 5}}>
          <CardExpiryElement  onChange={onExpiryChange} options={styles.cardNumberOptions}></CardExpiryElement>
        </div>
      </Col>
      <Col span={12}>
        <div style={{marginLeft: 5}}>
          <CardCvcElement onChange={onCVVChange} options={styles.cardNumberOptions}></CardCvcElement>

        </div>
      </Col>
    </Row>
    <div style={{
      display: "flex",
      justifyItem: "center",
      justifyContent: "flex-end",
      alignItem: "center",
      alignContent: "center",
    }}>

      <Button disabled={!(error.isCardNumberComplete && error.isExpiryCompleted && error.isCVCComplete)} style={{marginTop: 10}} loading={isLoading} onClick={onClickPressed} type="primary">Submit</Button>

    </div>
  </div>
}

const styles = {

  cardNumberOptions: {
    showIcon: true,
    style: {
      base: {
        fontSize: "16px",
        fontFamily: "inner, sans-serif",
        fontSmoothing: "inner",
        color: "#424770",
        "::placeholder": {
          color: "#aab7c4",
        },
      },
      invalid: {
        color: "#EF4444",
      },

    },
  }
}


export default StripeCheckoutForm
