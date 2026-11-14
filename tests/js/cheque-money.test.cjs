const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const context = {window:{}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/cheque-money.js'),'utf8'),context);
const money = context.window.ChequeMoney;

test('English, Persian and Arabic money round-trip without losing digits', () => {
    for (const value of ['1,234,567','۱٬۲۳۴٬۵۶۷','١٬٢٣٤٬٥٦٧','1 234 567']) {
        assert.equal(money.normalize(value),'1234567');
        assert.equal(money.format(value),'1,234,567');
    }
    assert.equal(money.format('1000000000000'),'1,000,000,000,000');
    assert.equal(money.format('0'),'0');
    assert.equal(money.format(''),'');
});
test('Withdrawals keep their sign; decimal/exponent input is not silently rescaled', () => {
    assert.equal(money.normalize(money.format('-۱۰۰۰۰')),'-10000');
    assert.equal(money.format('-10000'),'-10,000');
    assert.equal(money.format('1.5'),'1.5');
    assert.equal(money.format('1e3'),'1e3');
});
function input(value, attributes = {}) {
    return {value,dataset:{},selectionStart:value.length,
        getAttribute:key=>attributes[key]??null,
        setCustomValidity(message){this.message=message;},
        setSelectionRange(start,end){this.selectionStart=start;this.selectionEnd=end;}};
}
test('Caret remains beside the edited digits in the middle of the amount', () => {
    const field = input('12345');field.selectionStart=3;
    money.edit(field);
    assert.equal(field.value,'12,345');
    assert.equal(field.selectionStart,4);
});
test('Formatted payment ceilings and invalid decimal amounts remain enforced', () => {
    const field = input('1,000,001',{max:'1000000',min:'1'});
    assert.equal(money.validate(field),false);
    money.set(field,'1000000');assert.equal(field.message,'');
    field.value='1.5';assert.equal(money.validate(field),false);
    field.value='-1000';assert.equal(money.validate(field),false);
    field.dataset.moneySigned='1';assert.equal(money.validate(field),false); // min=1
    assert.equal(money.validate({...input('-1,000'),dataset:{moneySigned:'1'}}),true);
});
