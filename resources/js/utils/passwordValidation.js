export const hasLowercaseCharacter = (pw) => {
  var mediumRegex = new RegExp("(?=.*?[a-z])");
  return mediumRegex.test(pw)
}

export const hasUpperCaseAlphabetical = (pw) => {
  var mediumRegex = new RegExp("(?=.*?[A-Z])");
  return mediumRegex.test(pw)
}
export const hasNumericCharacter = (pw) => {
  var mediumRegex = new RegExp("(?=.*?[0-9])");
  return mediumRegex.test(pw)
}
export const hasSpecialCharacter = (pw) => {
  var mediumRegex = new RegExp("(?=.*?[#?!@$%^&*-])");
  return mediumRegex.test(pw)
}
export const hasEightCharacter = (pw) => {
  return (pw.length >= 8 && pw.length <= 100)
}
