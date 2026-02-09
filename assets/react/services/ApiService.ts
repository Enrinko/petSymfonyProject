export class ApiService {

    sendDeposit(amount: number): Promise<any> {
        return fetch('/deposit', {
            method: "POST",
            mode: "same-origin",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                'amount' : amount
            })
        });
    }

}
