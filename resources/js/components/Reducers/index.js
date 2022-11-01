import { combineReducers } from 'redux'
import authenticate from './authenticate'
import designerSettings from './designerSettings'

export default combineReducers({
  authenticate,
  designerSettings
})