export default {
  variantPriceCustomize (data) {
    let categoryDisplay = data.products[0].categoryDisplay;

    switch (categoryDisplay) {
      case 'Apparel':
        let imageID = [];

        data.selectedStageValues.map((value, index) => {
          value.stageGroups.map((stage) => {
            imageID.push(stage.stages[0].imageId)
          })
        })

        if (!imageID.includes(null)) {
          data.validOptions = data.validOptions.map(variant => {
            return  Object.assign(variant, {price: (Number(variant.price) + 5).toFixed(2)})
          })
          this.getSellingPrice(data);
        } else {
          this.getSellingPrice(data);
        }
        return

      default:
        return data.validOptions;
    }
  },

  getSellingPrice(data) {
    data.validOptions = data.validOptions.map(variant => {
      variant.defaultPrice = (Number(variant.price) * 2).toFixed(2);
      return variant;
    });
  }
}
